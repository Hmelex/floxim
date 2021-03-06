(function($) {
    
    function fx_front_panel() {
        var $body = $('#fx_admin_extra_panel .fx_admin_panel_body');
        var $footer = $('#fx_admin_extra_panel .fx_admin_panel_footer');
        var body_default_margin = null;
        this.is_visible = false;
        
        this.show_form = function(data, params) {
            if (!params.view) {
                params.view = data.view ? data.view : 'vertical';
            }
            this.is_visible = true;
            
            // disable hilight & select, hide node panel
            $fx.front.disable_hilight();
            $fx.front.disable_select();
            $fx.front.disable_node_panel();
            
            this.stop();
            $footer.html('').show().css('visibility', 'hidden');
            
            $body.css({height:'1px', 'visibility':'hidden'}).removeClass('fx_admin_panel_body_overflow_hidden').show();
            
            if (!data.fields) {
                data.fields = [];
            }
            
            data.fields.push({type:'hidden', name:'_base_url', value:document.location.href});
            
            if (params.view === 'horizontal') {
                $.each(data.fields, function(key, field) {
                    field.context = 'panel';
                });
            }
            
            if (!data.form_button) {
                data.form_button = ['save'];
            }
            data.form_button.unshift('cancel');
            data.class_name = 'fx_form_'+params.view;
            data.button_container = $footer;
            
            
            var $form = $fx.form.create(data, $body);
            
            $form.on('fx_form_cancel', function() {
                if (params.oncancel) {
                    params.oncancel($form);
                }
                $fx.front_panel.hide();
            }).on('keydown', function(e) {
                if (e.which === 27) {
                    $form.trigger('fx_form_cancel');
                    return false;
                }
            }).on('fx_form_sent', function(e, data) {
                if (data.status === 'error') {
                    
                } else {
                    $fx.front_panel.hide();
                    if (params.onfinish) {
                        params.onfinish(data);
                    }
                }
            });
            setTimeout(function() {
                $footer.css('visibility', 'visible');
                $body.css('visibility', 'visible');
                $fx.front_panel.animate_panel_height(null, function () {
                    $form.resize(function() {
                        $fx.front_panel.animate_panel_height();
                    });
                    $(':input:visible', $form).first().focus();
                    if (params.onready) {
                        params.onready($form);
                    }
                });
            }, 100);
        };
        
        this.animate_panel_height = function(panel_height, callback) {
            //return;
            if (this.is_moving) {
                return;
            }
            var max_height = Math.round(
                $(window).height() * 0.75
            );
            var $form = $('form', $body);
            
            var form_height = $form.outerHeight();
            
            if (typeof panel_height === 'undefined' || panel_height === null) {
                panel_height = Math.min(form_height, max_height);
                //console.log('cnt', form_height, max_height);
            }
            if (panel_height > 0) {
                $body.toggleClass('fx_admin_panel_body_overflow_hidden', form_height <= panel_height);
            }
            
            $form.css({'box-sizing':'border-box', 'width': '101%'});
            $form.css('width', '100%');
            
            if (panel_height === $body.height()) {
                return;
            }
            if (body_default_margin === null) {
                body_default_margin = parseInt($('body').css('margin-top'));
            }
            var body_offset = body_default_margin + panel_height;
            
            var height_delta = body_offset - parseInt($('body').css('margin-top'));
            this.is_moving = true;
            var duration = 300;
            $body.animate(
                {height: panel_height+'px'}, 
                duration, 
                function() {
                    $fx.front_panel.is_moving = false;
                }
            );
            
            // animate body within a named queue to avoid stopping other animations 
            // (mainly, opacity when layout is reloaded)
            $('body').animate(
                {'margin-top':body_offset + 'px'},
                {
                    duration:duration,
                    queue:'fx_panel_queue'
                }
            ).dequeue('fx_panel_queue');
    
            height_delta = (height_delta > 0 ? '+=' : '-=')+ Math.abs(height_delta);
            $('.fx_top_fixed').animate(
                {'top': height_delta}, 
                duration
            );
            $fx.front.get_front_overlay().animate({
                    top: height_delta
                }, {
                    duration:duration,
                    complete: callback
            });
        };
        
        this.stop = function() {
            $body.stop(true,false);
            $('body').stop('fx_panel_queue', true,false);
            $fx.front.get_front_overlay().stop(true,false);
            $('.fx_top_fixed').stop(true,false);
            this.is_moving =  false;
        };
        this.load_form = function(form_options, params) {
            if (form_options._base_url === undefined) {
                form_options._base_url = document.location.href;
            }
            $fx.post(
                form_options, 
                function(json) {
                    $fx.front_panel.show_form(json, params);
                }
            );
        };
        this.hide = function() {
            $fx.front.enable_select();
            $fx.front.enable_node_panel();
            this.animate_panel_height(0, function () {
                $body.hide().html('');
                $footer.hide();
                if (!$fx.front.get_selected_item()) {
                    $fx.front.enable_hilight();
                }
                $fx.front_panel.is_visible = false;
            });
        };
    };
    
    $(function() {
        $fx.front_panel = new fx_front_panel();
    });
})($fxj);