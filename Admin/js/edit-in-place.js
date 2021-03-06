(function($){
$.fn.edit_in_place = function(command) {
    var $nodes = this;
    $nodes.each(function() {
        var $node = $(this);
        var eip = $node.data('edit_in_place');
        if (!eip) {
            eip = new fx_edit_in_place($node);
        }
        if (!command) {
            return eip;
        }
        switch(command) {
            case 'destroy':
                eip.stop();
                break;
        }
    });
};

function fx_edit_in_place( node ) { 
    this.node = node;
    
    node.data('edit_in_place', this);
    node.addClass('fx_edit_in_place');
        
    this.panel_fields = [];
    this.is_content_editable = false;
    
    this.ib_meta = node.closest('.fx_infoblock').data('fx_infoblock');
    
    var eip = this;
    
    // need to edit the contents of the site
    if (this.node.data('fx_var')) {
        this.meta = node.data('fx_var');
        this.start(node.data('fx_var'));
    }
    // edit the attributes of the node
    for( var i in this.node.data()) {
        if (!/^fx_template_var/.test(i)) {
            continue;
        }
        var meta = this.node.data(i);
        meta.is_att = true;
        this.start(meta);
    }
    // edit fields from fx_controller_meta['field']
    var c_meta = this.node.data('fx_controller_meta');
    if (c_meta && c_meta.fields) {
        $.each(c_meta.fields, function(index, field) {
            eip.start(field);
        });
    }
}

fx_edit_in_place.prototype.handle_keydown = function(e) {
    if (e.which === 27) {
        if (e.isDefaultPrevented && e.isDefaultPrevented()) {
            return;
        }
        if ($('#redactor_modal:visible').length) {
            e.stopImmediatePropagation();
            return false;
        }
        this.stop();
        this.restore();
        $fx.front.deselect_item();
        //e.stopImmediatePropagation();
        return false;
    }
    if (e.which === 13 && (!this.is_wysiwyg || e.ctrlKey)) {
        this.save().stop();
        $(this.node).closest('a').blur();
        e.stopImmediatePropagation();
        return false;
    }
};

fx_edit_in_place.prototype.start = function(meta) {
	var edit_in_place = this;
        if (!meta.type) {
            meta.type = 'string';
        }
        this.node.trigger('fx_before_editing');
	switch (meta.type) {
            case 'datetime':
                this.add_panel_field(
                    $.extend({}, meta, {
                        value: meta.real_value //|| this.node.text()
                    })
                );
                break;
            case 'image': case 'file': 
                var field_meta = $.extend(
                    {}, 
                    meta, 
                    {real_value:{path: meta.real_value || ''}}
                );
                this.add_panel_field(
                    field_meta
                ).on('fx_change_file', function() {
                    edit_in_place.save().stop();
                });
                break;
            case 'select': case 'livesearch': case 'bool': case 'color': case 'map':
                this.add_panel_field(meta);
                break;
            case 'string': case 'html': case '': case 'text': case 'int': case 'float':
                if (meta.is_att) {
                    this.add_panel_field(meta);
                } else {
                    var $n = this.node;
                    this.is_content_editable = true;
                    if (!$($fx.front.get_selected_item()).hasClass('fx_entity')) {
                        setTimeout(function() {
                            $fx.front.stop_entities_sortable();
                        }, 50);
                    }
                    if ($n.hasClass('fx_hidden_placeholded')) {
                        this.was_placeholded_by = this.node.html();
                        $n.removeClass('fx_hidden_placeholded');
                        $n.html('');
                    }
                    
                    // create css stylesheet for placeholder color
                    // we cannot just append styles to an element, 
                    // because placeholder is implemented by css :before property
                    var c_color = window.getComputedStyle($n[0]).color.replace(/[^0-9,]/g, '').split(',');
                    var avg_color = (c_color[0]*1 + c_color[1]*1 + c_color[2]*1) / 3;
                    
                    $("<style type='text/css' class='fx_placeholder_stylesheet'>\n"+
                        ".fx_var_editable:empty:after {color:rgb("+avg_color+","+avg_color+","+avg_color+") !important;}"+
                    "</style>").appendTo( $('head') );
                    
                    $n.addClass('fx_var_editable');
                    $n.attr('fx_placeholder', meta.label || meta.name || meta.id);
                    
                    if ( (meta.type === 'text' && meta.html && meta.html !== '0') || meta.type === 'html') {
                        $n.data('fx_saved_value', $n.html());
                        this.is_wysiwyg = true;
                        this.make_wysiwyg();
                    } else {
                        $n.data('fx_saved_value', $n.text());
                        
                        // do not allow paste html into non-html fields
                        // this way seems to be ugly
                        // @todo onkeydown solution or clear node contents after real paste
                        $n.on('paste.edit_in_place', function(e) {
                            e.preventDefault();
                            document.execCommand(
                                'inserttext', 
                                false,
                                prompt('Paste your text here:')
                            );
                        });
                    }
                    
                    var handle_node_size = function () {
                        var text = $.trim($n.text());
                        var is_empty = text.length === 0 || (text.length === 1 && text.charCodeAt(0) === 8203);
                        if (is_empty && !edit_in_place.is_wysiwyg) {
                            $n.html('&#8203;');
                        }
                        $n.toggleClass(
                            'fx_editable_empty', 
                            is_empty
                        );
                        if (is_empty && !edit_in_place.is_wysiwyg) {
                            setTimeout(
                                function() {$n.focus();},
                                1
                            );
                        }
                    }; 
                    $n.attr('contenteditable', 'true').focus();
                    this.$closest_button = $n.closest('button');
                    if (this.$closest_button.length > 0) {
                        this.$closest_button.on('click.edit_in_place', function() {return false;});
                    }
                    if (!this.is_wysiwyg) {
                        handle_node_size();
                        $n.on(
                            'keyup.edit_in_place keydown.edit_in_place click.edit_in_place change.edit_in_place', 
                            function () {setTimeout(handle_node_size,1);}
                        );
                    }
                }
                break;
	}
        $('html').one('fx_deselect.edit_in_place', function(e) {
            edit_in_place.save().stop();
	}).on('keydown.edit_in_place', function(e) {
            edit_in_place.handle_keydown(e);
        });
        if (!this.is_content_editable && this.panel_fields.length) {
            setTimeout(function() {
                $(':input:visible', edit_in_place.panel_fields[0]).focus();
            }, 50);
        }
};

fx_edit_in_place.prototype.add_panel_field = function(meta) {
    if (meta.real_value) {
        meta.value = meta.real_value;
    }
    meta = $.extend({}, meta);
    
    if (meta.var_type === 'visual') {
        meta.name = meta.id;
    }
    if (!meta.type) {
        meta.type = 'string';
    }
    
    if (!meta.label) {
        meta.label = meta.id;
    }
    
    var $field_container = $fx.front.get_node_panel();
    $field_container.show();
    var field_node = $fx_form.draw_field(meta, $field_container);
    if (meta.type !== 'livesearch') {
        field_node.css({'outline-style': 'solid','outline-color':'#FFF'});
        field_node.find(':input').css({'background':'transparent'});
        field_node.animate(
            {
                'background-color':'#FF0', 
                'outline-width':'6px',
                'outline-color':'#FF0'
            },
            300,
            null,
            function() {
                field_node.animate(
                    {
                        'background-color':'#FFF', 
                        'outline-width':'0px',
                        'outline-color':'#FFF'
                    },
                    300
                );
            }
        );
    }
    field_node.data('meta', meta);
    this.panel_fields.push(field_node);
    return field_node;
};

fx_edit_in_place.prototype.stop = function() {
    this.node.data('edit_in_place', null);
    this.node.removeClass('fx_edit_in_place').removeClass('fx_editable_empty');
    if (this.stopped) {
        return this;
    }
    for (var i =0 ;i<this.panel_fields.length; i++) {
        this.panel_fields[i].remove();
    }
    this.panel_fields = [];
    this.node.data('edit_in_place', null);
    this.node.attr('contenteditable', null);
    this.node.removeClass('fx_var_editable');
    if (this.is_content_editable && this.is_wysiwyg) {
        this.destroy_wysiwyg();
    }
    $('*').off('.edit_in_place');
    this.node.blur();
    this.stopped = true;
    if (this.was_placeholded_by && this.node.text().match(/^\s*$/)) {
        this.node.addClass('fx_hidden_placeholded').html(this.was_placeholded_by);
    }
    $('head .fx_placeholder_stylesheet').remove();
    return this;
};

/**
 * Clear extra \n after block level tags inserted by Redactor 
 * see method cleanParagraphy() in Redactor's source code
 */
fx_edit_in_place.prototype.clear_redactor_val = function (v) {
    // pre removed
    var r_blocks = '(comment|html|body|head|title|meta|style|script|link|iframe|table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
    var rex = new RegExp('[\\s\\t\\n\\r]*(</?'+r_blocks+'[^>]*?>)[\\s\\t\\n\\r]*', 'ig');
    v = v.replace(rex, '$1');
    var $temp = $('<div contenteditable="true"></div>');
    $temp.html(v);
    v = $temp.html();
    $temp.remove();
    return v;
};

fx_edit_in_place.prototype.get_vars = function() {
    var node = this.node;
    var vars = [];
    // edit the text node
    var is_content_editable = this.is_content_editable;
    if (is_content_editable) {
        if (this.is_wysiwyg) {
            if (this.source_area.is(':visible')) {
                this.node.redactor('toggle');
            }
            $('.fx_internal_block', this.node).trigger('fx_stop_editing');
        }
        var saved_val = $.trim(node.data('fx_saved_value'));
        var is_changed = false;
        if (this.is_wysiwyg) {
            var new_val = $.trim(node.redactor('get'));
            var clear_new = this.clear_redactor_val(new_val);
            var clear_old = this.clear_redactor_val(saved_val);
            
            is_changed = clear_new !== clear_old;
            
            /*
            if (is_changed) {
                for (var li = 0; li < clear_new.length; li++) {
                    if (clear_new[li] !== clear_old[li]) {
                        console.log(li+' new: '+clear_new.slice(li-20, li+35));
                        console.log(li+' old: '+clear_old.slice(li-20, li+35));
                        break;
                    }
                }
            }
            */
        } else {
            var new_val = $.trim(node.text());
            
            // handle zero-width space
            if (new_val.charCodeAt(new_val.length - 1) === 8203) {
                new_val = new_val.substring(0, new_val.length - 1);
            }
            // put empty val instead of zero-width space
            if (!new_val) {
                node.html(new_val);
            }
            is_changed = new_val !== saved_val;
        }
        
        if (is_changed) {
            vars.push({
                'var':this.meta,
                'value':new_val
            });
        }
    }
    for (var i = 0; i < this.panel_fields.length; i++) {
        var pf = this.panel_fields[i];
        var pf_meta = pf.data('meta');
        if (!pf_meta) {
            console.log('no meta', this.panel_fields[i], this);
            continue;
        }
        var old_value = pf_meta.value;
        if (pf_meta.type === 'bool') {
            var c_input = $('input[name="'+pf_meta['name']+'"][type="checkbox"]', pf);
            var new_value = c_input.is(':checked') ? "1" : "0";
            if (old_value === null) {
                old_value = '0';
            }
        } else if (pf_meta.type === 'livesearch') {
            var livesearch = $('.livesearch', pf).data('livesearch');
            var new_value = livesearch.getValues();
            // if the loaded value contained full objects (with name and id) 
            // let's convert it to the same format as new value has - plain array of ids
            // we copy old value
            if (old_value instanceof Array) {
                var old_copy = [];
                for (var old_index = 0; old_index < old_value.length; old_index++) {
                    var old_item = old_value[old_index];
                    if (typeof old_item === 'object') {
                        old_copy[old_index] = old_item.id;
                    } else {
                        old_copy[old_index] = old_item;
                    }
                }
                old_value = old_copy;
            }
                
        } else {
            var new_value = $(':input[name="'+pf_meta['name']+'"]', pf).val();
        }
        
        var value_changed = false;
        if (pf_meta.type === 'image' || pf_meta.type === 'file') {
            value_changed = new_value !== old_value.path;
        } else if (new_value instanceof Array && old_value instanceof Array) {
            value_changed = new_value.join(',') !== old_value.join(',');
        } else {
            if (old_value === undefined && new_value === '') {
                value_changed = false;
            } else {
                value_changed = new_value !== old_value;
            }
        }
        if (value_changed) {
            vars.push({
                'var': pf_meta,
                value:new_value
            });
        }
    }
    return vars;
};

fx_edit_in_place.prototype.save = function() {
    if (this.stopped) {
        return this;
    }
    var vars = [];
    var $edited = $('.fx_edit_in_place');
    $edited.each(function() {
        var c_eip = $(this).data('edit_in_place');
        if (c_eip){
            $.each(c_eip.get_vars(), function(index, item) {
                vars.push(item);
            });
        }
    });
    
    
    // nothing has changed
    if (vars.length === 0) {
        this.stop();
        this.restore();
        return this;
    }
    var new_entity_props = null;
    var $adder_placeholder = $(this.node).closest('.fx_entity_adder_placeholder');
    if ($adder_placeholder.length > 0) {
        var entity_meta = $adder_placeholder.data('fx_entity_meta');
        new_entity_props = entity_meta ? entity_meta.placeholder : null;
    }
    
    var post_data = {
        entity:'infoblock',
        action:'save_var',
        infoblock:this.ib_meta,
        vars: vars,
        fx_admin:true,
        page_id:$fx.front.get_page_id()
    };
    if (new_entity_props) {
        post_data.new_entity_props = new_entity_props;
    }
    
    var node = this.node;
    $fx.front.disable_infoblock(node.closest('.fx_infoblock'));
    
    $fx.post(
        post_data, 
        function() {
            $fx.front.reload_infoblock(node.closest('.fx_infoblock').get(0));
	}
    );
    return this;
};

fx_edit_in_place.prototype.restore = function() {
    if (!this.is_content_editable || this.was_placeholded_by) {
        return this;
    }
    var saved = this.node.data('fx_saved_value');
    this.node.html(saved);
    this.node.trigger('fx_editable_restored');
    return this;
};

fx_edit_in_place.prototype.make_wysiwyg = function () {
    var sel = window.getSelection(),
        $node = this.node,
        node = $node[0];
    if (sel && $.contains(node, sel.focusNode)) {
        var range = sel.getRangeAt(0);
        range.collapse(true);
        var click_range_offset = range.startOffset,
            $range_text_node = $(range.startContainer),
            c_text = $range_text_node[0],
            range_text_position = 0;
        while (c_text.previousSibling){
            c_text = c_text.previousSibling;
            range_text_position++;
        };
        $range_text_node.parent().addClass('fx_click_range_marker');
        range.detach();
    }
    if (!$node.attr('id')) {
        $node.attr('id', 'stub'+Math.round(Math.random()*1000));
    }
    var $panel = $fx.front.get_node_panel();
    $panel.append('<div class="editor_panel" />').show();
    var linebreaks = this.meta.var_type === 'visual';
    if (this.meta.linebreaks !== undefined) {
        linebreaks = !!this.meta.linebreaks;
    }
    $fx_fields.make_redactor($node, {
        linebreaks:linebreaks,
        placeholder:false,
        toolbarExternal: '.editor_panel',
        initCallback: function() {
            
            
            var $box = $node.closest('.redactor_box');
            $box.after($node);
            $('body').append($box);
            $node.data('redactor_box', $box);
            
            var $range_node = $node.parent().find('.fx_click_range_marker');
            if ($range_node.length) {
                var range_text = $range_node[0].childNodes[range_text_position];
                if (!range_text && $range_node[0].childNodes.length > 0) {
                    range_text = $range_node[0].childNodes[0];
                    click_range_offset = 0;
                }
                if (range_text && range_text.nodeType === 3) {
                    var selection = window.getSelection(),
                        range = document.createRange();
                    if (click_range_offset > range_text.length) {
                        click_range_offset = range_text.length;
                    }
                    range.setStart(range_text, click_range_offset);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                $range_node.removeClass('fx_click_range_marker');
            }
            this.sync();
        }
    });
    this.source_area = $('textarea[name="'+ $node.attr('id')+'"]');
    this.source_area.addClass('fx_overlay');
    this.source_area.css({
        position:'relative',
        top:'0px',
        left:'0px'
    });
};

fx_edit_in_place.prototype.destroy_wysiwyg = function() {
    this.node.before(this.node.data('redactor_box'));
    this.node.redactor('destroy');
    $('#fx_admin_control .editor_panel').remove();
    this.node.get(0).normalize();
};

$(function() {
    for (var i = 0; i < document.styleSheets.length; i++) {
        var sheet = document.styleSheets[i];
        try {
            if (!sheet.cssRules) {
                continue;
            }
        } catch (e) {
            continue;
        }
        
        for (var j = 0; j < sheet.cssRules.length; j++) {
            var rule = sheet.cssRules[j];
            if (rule.type !== 1 || !rule.cssText) {
                continue;
            }
            if (rule.selectorText.match(/\.redactor_editor/)) {
                var new_css = rule.cssText.replace(/\.redactor_editor/g, '.redactor_fx_wysiwyg');
                sheet.deleteRule(j);
                sheet.insertRule(
                    new_css,
                    j
                );
            } else if ( rule.selectorText === '.redactor_box') {
                sheet.deleteRule(j);
            }
        }
    }
});

})($fxj);