!function(e){var t={};function i(n){if(t[n])return t[n].exports;var s=t[n]={i:n,l:!1,exports:{}};return e[n].call(s.exports,s,s.exports,i),s.l=!0,s.exports}i.m=e,i.c=t,i.d=function(e,t,n){i.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:n})},i.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},i.t=function(e,t){if(1&t&&(e=i(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(i.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var s in e)i.d(n,s,function(t){return e[t]}.bind(null,s));return n},i.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return i.d(t,"a",t),t},i.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},i.p="",i(i.s=3)}([function(e,t){!function(){const e=function(){const e=jQuery("#field-video_player").val(),t=jQuery("#field-video_controls").prop("checked"),i=jQuery('#field-video_autoplay_mode option[value="off"]');"cld"!==e||t?i.prop("disabled",!1):(i.prop("disabled",!0),i.prop("selected")&&i.next().prop("selected",!0))};e(),jQuery(document).on("change","#field-video_player",e),jQuery(document).on("change","#field-video_controls",e),jQuery(document).ready((function(e){e(document).on("tabs.init",(function(){var t=e(".settings-tab-trigger"),i=e(".settings-tab-section");e(this).on("click",".settings-tab-trigger",(function(n){var s=e(this),a=e(s.attr("href"));n.preventDefault(),t.removeClass("active"),i.removeClass("active"),s.addClass("active"),a.addClass("active"),e(document).trigger("settings.tabbed",s)})),e(".cld-field").not('[data-condition="false"]').each((function(){const t=e(this),i=t.data("condition");for(let n in i){const s=i[n],a=e("#field-"+n),o=t.closest("tr");a.on("change init",(function(){this.value===s||this.checked?o.show():o.hide()})),a.trigger("init")}})),e("#field-cloudinary_url").on("input change",(function(){let t=e(this),i=t.val();new RegExp(/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d)*[:]{1}[^:@]*[@]{1}[^@]*$/g).test(i)?(t.addClass("settings-valid-field"),t.removeClass("settings-invalid-field")):(t.removeClass("settings-valid-field"),t.addClass("settings-invalid-field"))})).trigger("change")})),e(".render-trigger[data-event]").each((function(){var t=e(this),i=t.data("event");t.trigger(i,this)}))}))}(window,jQuery)},function(e,t){if(wp.media&&window.CLDN){wp.media.events.on("editor:image-edit",(function(e){e.metadata.cldoverwrite=null,e.image.className.split(" ").indexOf("cld-overwrite")>=0&&(e.metadata.cldoverwrite="true")})),wp.media.events.on("editor:image-update",(function(e){let t=e.image.className.split(" ");e.metadata.cldoverwrite&&-1===t.indexOf("cld-overwrite")?t.push("cld-overwrite"):!e.metadata.cldoverwrite&&t.indexOf("cld-overwrite")>=0&&delete t[t.indexOf("cld-overwrite")],e.image.className=t.join(" ")}));let e=null,t=wp.media.string.props;wp.media.string.props=function(i,n){return i.cldoverwrite&&(i.classes=["cld-overwrite"],e=!0),t(i,n)},wp.media.post=function(t,i){if("send-attachment-to-editor"===t){let t=wp.media.editor.get().state().get("selection").get(i.attachment);t.attributes.transformations&&(i.attachment.transformations=t.attributes.transformations),(i.html.indexOf("cld-overwrite")>-1||!0===e)&&(i.attachment.cldoverwrite=!0,e=null)}return wp.ajax.post(t,i)};wp.media.controller.Library;let i=wp.media.view.MediaFrame.Select,n=wp.media.view.MediaFrame.Post,s=wp.media.view.MediaFrame.ImageDetails,a=wp.media.view.MediaFrame.VideoDetails,o=wp.media.View.extend({tagName:"div",className:"cloudinary-widget",template:wp.template("cloudinary-dam"),active:!1,toolbar:null,frame:null,ready:function(){let e=this.controller,t=this.model.get("selection"),i=this.model.get("library"),n=wp.media.model.Attachment;if(CLDN.mloptions.multiple=e.options.multiple,this.cid!==this.active){if(CLDN.mloptions.inline_container="#cloudinary-dam-"+e.cid,1===t.length){var s=n.get(t.models[0].id);void 0!==s.attributes.public_id&&(CLDN.mloptions.asset={resource_id:s.attributes.public_id})}else CLDN.mloptions.asset=null;window.ml=cloudinary.openMediaLibrary(CLDN.mloptions,{insertHandler:function(s){for(let a=0;a<s.assets.length;a++){let o=s.assets[a];wp.media.post("cloudinary-down-sync",{nonce:CLDN.nonce,asset:o}).done((function(s){let a=function(e,t){e.uploading=!1,t.set(e),wp.Uploader.queue.remove(t),0===wp.Uploader.queue.length&&wp.Uploader.queue.reset()};if(void 0!==s.fetch){let e=n.get(s.attachment_id);e.set(s),i.add(e),wp.Uploader.queue.add(e),wp.ajax.send({url:s.fetch,beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",CLDN.nonce)},data:{src:s.url,filename:s.filename,attachment_id:s.attachment_id,transformations:s.transformations}}).done((function(e){let t=n.get(e.id);a(e,t)})).fail((function(n){a(s,e),i.remove(e),t.remove(e),"string"==typeof n?alert(n):500===n.status&&alert("HTTP error.")}))}else{let e=n.get(s.id);e.set(s),t.add(e)}0===wp.Uploader.queue.length&&wp.Uploader.queue.reset(),e.content.mode("browse")}))}}},document.querySelectorAll(".dam-cloudinary")[0])}return this.active=this.cid,this}}),r=function(e){return{bindHandlers:function(){e.prototype.bindHandlers.apply(this,arguments),this.on("content:render:cloudinary",this.cloudinaryContent,this)},browseRouter:function(t){e.prototype.browseRouter.apply(this,arguments);this.state().get("id");t.set({cloudinary:{text:"Cloudinary",priority:60}})},cloudinaryContent:function(e){let t=this.state(),i=new o({controller:this,model:t}).render();this.content.set(i)}}};wp.media.view.MediaFrame.Select=i.extend(r(i)),wp.media.view.MediaFrame.Post=n.extend(r(n)),wp.media.view.MediaFrame.ImageDetails=s.extend(r(s)),wp.media.view.MediaFrame.VideoDetails=a.extend(r(a))}},function(e,t,i){},function(e,t,i){"use strict";i.r(t);var n=i(0),s=i.n(n);const a={progress:document.getElementById("progress-wrapper"),submitButton:document.getElementById("submit"),stopButton:document.getElementById("stop-sync"),completed:document.getElementById("completed-notice"),show:"inline-block",hide:"none",isRunning:!1,getStatus:function(){var e=cloudinaryApi.restUrl+"cloudinary/v1/attachments";wp.ajax.send({url:e,type:"GET",beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){a.isRunning=e.is_running,a.isRunning&&setTimeout(a.getStatus,1e4),a._updateUI(e)}))},stopSync:function(){var e=cloudinaryApi.restUrl+"cloudinary/v1/sync";a.isRunning=!1,wp.ajax.send({url:e,data:{stop:!0},beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){a._updateUI(e)}))},pushAttachments:function(){var e=cloudinaryApi.restUrl+"cloudinary/v1/sync";a.isRunning=!0,a.progress.style.display=a.show,wp.ajax.send({url:e,beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){setTimeout(a.getStatus,1e4)}))},_updateUI:function(e){e.percent<100&&void 0!==e.started?(this.submitButton.style.display=this.hide,this.stopButton.style.display=this.show):e.percent>=100&&void 0!==e.started?(this.submitButton.style.display=this.hide,this.stopButton.style.display=this.show):e.pending>0?(this.submitButton.style.display=this.show,this.stopButton.style.display=this.hide):e.processing>0?this.stopButton.style.display=this.show:this.stopButton.style.display=this.hide,100===e.percent&&(this.completed.style.display=this.show),this.isRunning?this.progress.style.display=this.show:this.progress.style.display=this.hide},_start:function(e){e.preventDefault(),a.stopButton.style.display=a.show,a.submitButton.style.display=a.hide,a.pushAttachments()},_reset:function(e){a.submitButton.style.display=a.hide,a.getStatus()},_init:function(e){"undefined"!=typeof cloudinaryApi&&((document.attachEvent?"complete"===document.readyState:"loading"!==document.readyState)?e():document.addEventListener("DOMContentLoaded",e))}};var o=a;a._init((function(){a._reset(),a.submitButton.addEventListener("click",a._start),a.stopButton.addEventListener("click",a.stopSync)}));var r=i(1),l=i.n(r);const d={sample:{image:document.getElementById("transformation-sample-image"),video:document.getElementById("transformation-sample-video")},preview:{image:document.getElementById("sample-image"),video:document.getElementById("sample-video")},fields:document.getElementsByClassName("cld-field"),button:{image:document.getElementById("refresh-image-preview"),video:document.getElementById("refresh-video-preview")},spinner:{image:document.getElementById("image-loader"),video:document.getElementById("video-loader")},activeItem:null,elements:{image:[],video:[]},_placeItem:function(e){null!==e&&(e.style.display="block",e.style.visibility="visible",e.style.position="absolute",e.style.top=e.parentElement.clientHeight/2-e.clientHeight/2+"px",e.style.left=e.parentElement.clientWidth/2-e.clientWidth/2+"px")},_setLoading:function(e){this.button[e].style.display="block",this._placeItem(this.button[e]),this.preview[e].style.opacity="0.1"},_build:function(e){this.sample[e].innerHTML="",this.elements[e]=[];for(let t of this.fields){if(e!==t.dataset.context)continue;let i=t.value.trim();if(i.length){if("select-one"===t.type){if("none"===i)continue;i=t.dataset.meta+"_"+i}else{let e=t.dataset.context;i=this._transformations(i,e,!0)}i&&this.elements[e].push(i)}}let t="";this.elements[e].length&&(t="/"+this.elements[e].join(",").replace(/ /g,"%20")),this.sample[e].textContent=t,this.sample[e].parentElement.href="https://res.cloudinary.com/demo/"+this.sample[e].parentElement.innerText.trim().replace("../","").replace(/ /g,"%20")},_clearLoading:function(e){this.spinner[e].style.visibility="hidden",this.activeItem=null,this.preview[e].style.opacity=1},_refresh:function(e,t){e&&e.preventDefault();let i=this,n=CLD_GLOBAL_TRANSFORMATIONS[t].preview_url+i.elements[t].join(",")+CLD_GLOBAL_TRANSFORMATIONS[t].file;if(this.button[t].style.display="none",this._placeItem(this.spinner[t]),"image"===t){let e=new Image;e.onload=function(){i.preview[t].src=this.src,i._clearLoading(t),e.remove()},e.onerror=function(){alert(CLD_GLOBAL_TRANSFORMATIONS[t].error),i._clearLoading(t)},e.src=n}else{let e=i._transformations(i.elements[t].join(","),t);samplePlayer.source({publicId:"dog",transformation:e}),i._clearLoading(t)}},_transformations:function(e,t,i=!1){let n=CLD_GLOBAL_TRANSFORMATIONS[t].valid_types,s=null,a=e.split("/"),o=[];for(let e=0;e<a.length;e++){let s,r=a[e].split(",");s=!0===i?[]:{};for(let e=0;e<r.length;e++){let a=r[e].trim().split("_");if(a.length<=1||void 0===n[a[0]])continue;let o=a.shift(),l=a.join("_");if(!0===i){if("f"===o||"q"===o)for(let e in this.elements[t])o+"_"===this.elements[t][e].substr(0,2)&&this.elements[t].splice(e,1);s.push(r[e])}else s[n[o]]=l.trim()}let l=0;l=!0===i?s.length:Object.keys(s).length,l&&(!0===i&&(s=s.join(",")),o.push(s))}return o.length&&(s=!0===i?o.join("/").trim():o),s},_reset:function(){for(let e of this.fields)e.value=null;for(let e in this.button)this._build(e),this._refresh(null,e)},_input:function(e){if(void 0!==e.dataset.context&&e.dataset.context.length){let t=e.dataset.context;this._setLoading(t),this._build(t)}},_init:function(){if("undefined"!=typeof CLD_GLOBAL_TRANSFORMATIONS){let e=this;document.addEventListener("DOMContentLoaded",(function(t){for(let t in e.button)e.button[t]&&e.button[t].addEventListener("click",(function(i){e._refresh(i,t)}));for(let t of e.fields)t.addEventListener("input",(function(){e._input(this)})),t.addEventListener("change",(function(){e._input(this)}));for(let t in CLD_GLOBAL_TRANSFORMATIONS)e._build(t),e._refresh(null,t)})),jQuery(document).ajaxComplete((function(t,i,n){-1!==n.data.indexOf("action=add-tag")&&-1===i.responseText.indexOf("wp_error")&&e._reset()}))}}};d._init();var c=d;const u={template:"",tags:jQuery("#cld-tax-items"),tagDelimiter:window.tagsSuggestL10n&&window.tagsSuggestL10n.tagDelimiter||",",startId:null,_init:function(){if(!this.tags.length)return;const e=this;this._sortable(),"undefined"!=typeof wpAjax&&(wpAjax.procesParseAjaxResponse=wpAjax.parseAjaxResponse,wpAjax.parseAjaxResponse=function(t,i,n){let s=wpAjax.procesParseAjaxResponse(t,i,n);if(!s.errors&&s.responses[0]&&jQuery('[data-taxonomy="'+s.responses[0].what+'"]').length){const t=jQuery(s.responses[0].data).find("label").last().text().trim();e._pushItem(s.responses[0].what,t)}return s}),void 0!==window.tagBox&&(window.tagBox.processflushTags=window.tagBox.flushTags,window.tagBox.flushTags=function(t,i,n){if(void 0===n){let n,o;const r=t.prop("id"),l=jQuery("input.newtag",t);for(var s in n=(i=i||!1)?jQuery(i).text():l.val(),o=window.tagBox.clean(n).split(e.tagDelimiter),o){var a=r+":"+o[s];jQuery('[data-item="'+a+'"]').length||e._pushItem(a,o[s])}}return this.processflushTags(t,i,n)},window.tagBox.processTags=window.tagBox.parseTags,window.tagBox.parseTags=function(t){const i=t.id,n=i.split("-check-num-")[1],s=i.split("-check-num-")[0],a=jQuery(t).closest(".tagsdiv").find(".the-tags"),o=window.tagBox.clean(a.val()).split(e.tagDelimiter)[n];(new wp.api.collections.Tags).fetch({data:{slug:o}}).done(i=>{const n=!!i.length&&jQuery('[data-item="'+s+":"+i[0].id+'"]');n.length?n.remove():(jQuery(`.cld-tax-order-list-item:contains(${o})`).remove(),--e.startId),this.processTags(t)})}),jQuery("body").on("change",".selectit input",(function(){const t=jQuery(this),i=t.val(),n=t.is(":checked"),s=t.parent().text().trim();!0===n?e.tags.find(`[data-item="category:${i}"]`).length||e._pushItem(`category:${i}`,s):e.tags.find(`[data-item="category:${i}"]`).remove()}))},_createItem:function(e,t){const i=jQuery("<li/>"),n=jQuery("<span/>"),s=jQuery("<input/>");return i.addClass("cld-tax-order-list-item").attr("data-item",e),s.addClass("cld-tax-order-list-item-input").attr("type","hidden").attr("name","cld_tax_order[]").val(e),n.addClass("dashicons dashicons-menu cld-tax-order-list-item-handle"),i.append(n).append(t).append(s),i},_pushItem:function(e,t){let i=this._createItem(e,t);this.tags.append(i)},_sortable:function(){jQuery(".cld-tax-order-list").sortable({connectWith:".cld-tax-order",axis:"y",handle:".cld-tax-order-list-item-handle",placeholder:"cld-tax-order-list-item-placeholder",forcePlaceholderSize:!0,helper:"clone"})}};if(void 0!==window.CLDN&&(u._init(),jQuery("[data-wp-lists] .selectit input[checked]").map((e,t)=>{jQuery(t).trigger("change")})),wp.data&&wp.data.select("core/editor")){const e={};wp.data.subscribe((function(){let t=wp.data.select("core").getTaxonomies();if(t)for(let i in t){const n=wp.data.select("core/editor").getEditedPostAttribute(t[i].rest_base);e[t[i].slug]=n}}));const t=wp.element.createElement,i=i=>{class n extends i{constructor(e){super(e),this.currentItems=jQuery(".cld-tax-order-list-item").map((e,t)=>jQuery(t).data("item")).get()}makeItem(e){if(this.currentItems.includes(this.getId(e)))return;const t=this.makeElement(e);jQuery("#cld-tax-items").append(t)}removeItem(e){const t=jQuery(`[data-item="${this.getId(e)}"]`);t.length&&(t.remove(),this.currentItems=this.currentItems.filter(t=>t!==this.getId(e)))}findOrCreateTerm(e){return(e=super.findOrCreateTerm(e)).then(e=>this.makeItem(e)),e}onChange(t){super.onChange(t);const i=this.pickItem(t);i&&(e[this.props.slug].includes(i.id)?this.makeItem(i):this.removeItem(i))}pickItem(e){if("object"==typeof e){if(e.target){for(let t in this.state.availableTerms)if(this.state.availableTerms[t].id===parseInt(e.target.value))return this.state.availableTerms[t]}else if(Array.isArray(e)){let t=this.state.selectedTerms.filter(t=>!e.includes(t))[0];return void 0===t&&(t=e.filter(e=>!this.state.selectedTerms.includes(e))[0]),this.state.availableTerms.find(e=>e.name===t)}}else if("number"==typeof e){for(let t in this.state.availableTerms)if(this.state.availableTerms[t].id===e)return this.state.availableTerms[t]}else{let t;if(e.length>this.state.selectedTerms.length)for(let i in e)-1===this.state.selectedTerms.indexOf(e[i])&&(t=e[i]);else for(let i in this.state.selectedTerms)-1===e.indexOf(this.state.selectedTerms[i])&&(t=this.state.selectedTerms[i]);for(let e in this.state.availableTerms)if(this.state.availableTerms[e].name===t)return this.state.availableTerms[e]}}getId(e){return`${this.props.slug}:${e.id}`}makeElement(e){const t=jQuery("<li/>"),i=jQuery("<span/>"),n=jQuery("<input/>");return t.addClass("cld-tax-order-list-item").attr("data-item",this.getId(e)),n.addClass("cld-tax-order-list-item-input").attr("type","hidden").attr("name","cld_tax_order[]").val(this.getId(e)),i.addClass("dashicons dashicons-menu cld-tax-order-list-item-handle"),t.append(i).append(e.name).append(n),t}}return e=>t(n,e)};wp.hooks.addFilter("editor.PostTaxonomyType","cld",i)}var p=u;const m={wpWrap:document.getElementById("wpwrap"),wpContent:document.getElementById("wpbody-content"),libraryWrap:document.getElementById("cloudinary-embed"),_init:function(){let e=this;"undefined"!=typeof CLD_ML&&(cloudinary.openMediaLibrary(CLD_ML.mloptions,{insertHandler:function(e){alert("Import is not yet implemented.")}}),window.addEventListener("resize",(function(t){e._resize()})),e._resize())},_resize:function(){let e=getComputedStyle(this.wpContent);this.libraryWrap.style.height=this.wpWrap.offsetHeight-parseInt(e.getPropertyValue("padding-bottom"))+"px"}};var h=m;m._init();i(2);i.d(t,"cloudinary",(function(){return f}));window.$=window.jQuery;const f={settings:s.a,sync:o,widget:l.a,Global_Transformations:c,Terms_Order:p,Media_Library:h}}]);