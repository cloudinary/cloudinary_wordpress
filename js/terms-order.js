!function(e){var t={};function a(r){if(t[r])return t[r].exports;var n=t[r]={i:r,l:!1,exports:{}};return e[r].call(n.exports,n,n.exports,a),n.l=!0,n.exports}a.m=e,a.c=t,a.d=function(e,t,r){a.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},a.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},a.t=function(e,t){if(1&t&&(e=a(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(a.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var n in e)a.d(r,n,function(t){return e[t]}.bind(null,n));return r},a.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return a.d(t,"a",t),t},a.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},a.p="",a(a.s=163)}({163:function(e,t,a){"use strict";a.r(t);var r={template:"",tags:jQuery("#cld-tax-items"),tagDelimiter:window.tagsSuggestL10n&&window.tagsSuggestL10n.tagDelimiter||",",startId:null,_init:function(){if(this.tags.length){var e=this;this._sortable(),"undefined"!=typeof wpAjax&&(wpAjax.procesParseAjaxResponse=wpAjax.parseAjaxResponse,wpAjax.parseAjaxResponse=function(t,a,r){var n=wpAjax.procesParseAjaxResponse(t,a,r);if(!n.errors&&n.responses[0]&&jQuery('[data-taxonomy="'+n.responses[0].what+'"]').length){var i=jQuery(n.responses[0].data).find("label").last().text().trim();e._pushItem(n.responses[0].what,i)}return n}),void 0!==window.tagBox&&(window.tagBox.processflushTags=window.tagBox.flushTags,window.tagBox.flushTags=function(t,a,r){if(void 0===r){var n=t.prop("id"),i=jQuery("input.newtag",t),o=(a=a||!1)?jQuery(a).text():i.val(),s=window.tagBox.clean(o).split(e.tagDelimiter);for(var l in s){var d=n+":"+s[l];jQuery('[data-item="'+d+'"]').length||e._pushItem(d,s[l])}}return this.processflushTags(t,a,r)},window.tagBox.processTags=window.tagBox.parseTags,window.tagBox.parseTags=function(t){var a=this,r=t.id,n=r.split("-check-num-")[1],i=r.split("-check-num-")[0],o=jQuery(t).closest(".tagsdiv").find(".the-tags"),s=window.tagBox.clean(o.val()).split(e.tagDelimiter)[n];(new wp.api.collections.Tags).fetch({data:{slug:s}}).done((function(r){var n=!!r.length&&jQuery('[data-item="'+i+":"+r[0].id+'"]');n.length?n.remove():(jQuery(".cld-tax-order-list-item:contains(".concat(s,")")).remove(),--e.startId),a.processTags(t)}))}),jQuery("body").on("change",".selectit input",(function(){var t=jQuery(this),a=t.val(),r=t.is(":checked"),n=t.parent().text().trim();!0===r?e.tags.find('[data-item="category:'.concat(a,'"]')).length||e._pushItem("category:".concat(a),n):e.tags.find('[data-item="category:'.concat(a,'"]')).remove()}))}},_createItem:function(e,t){var a=jQuery("<li/>"),r=jQuery("<span/>"),n=jQuery("<input/>");return a.addClass("cld-tax-order-list-item").attr("data-item",e),n.addClass("cld-tax-order-list-item-input").attr("type","hidden").attr("name","cld_tax_order[]").val(e),r.addClass("dashicons dashicons-menu cld-tax-order-list-item-handle"),a.append(r).append(t).append(n),a},_pushItem:function(e,t){var a=this._createItem(e,t);this.tags.append(a)},_sortable:function(){jQuery(".cld-tax-order-list").sortable({connectWith:".cld-tax-order",axis:"y",handle:".cld-tax-order-list-item-handle",placeholder:"cld-tax-order-list-item-placeholder",forcePlaceholderSize:!0,helper:"clone"})}};void 0!==window.CLDN&&(r._init(),jQuery("[data-wp-lists] .selectit input[checked]").each((function(e,t){jQuery(t).trigger("change")}))),t.default=r}});