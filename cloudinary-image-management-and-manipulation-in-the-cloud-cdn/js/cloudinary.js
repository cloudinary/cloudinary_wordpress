!function(e){var t={};function n(i){if(t[i])return t[i].exports;var s=t[i]={i:i,l:!1,exports:{}};return e[i].call(s.exports,s,s.exports,n),s.l=!0,s.exports}n.m=e,n.c=t,n.d=function(e,t,i){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:i})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var i=Object.create(null);if(n.r(i),Object.defineProperty(i,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var s in e)n.d(i,s,function(t){return e[t]}.bind(null,s));return i},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=4)}([function(e,t){!function(){const e=function(){const e=jQuery("#field-video_player").val(),t=jQuery("#field-video_controls").prop("checked"),n=jQuery('#field-video_autoplay_mode option[value="off"]');"cld"!==e||t?n.prop("disabled",!1):(n.prop("disabled",!0),n.prop("selected")&&n.next().prop("selected",!0))};e(),jQuery(document).on("change","#field-video_player",e),jQuery(document).on("change","#field-video_controls",e),jQuery(document).ready((function(e){e(document).on("tabs.init",(function(){var t=e(".settings-tab-trigger"),n=e(".settings-tab-section");e(this).on("click",".settings-tab-trigger",(function(i){var s=e(this),a=e(s.attr("href"));i.preventDefault(),t.removeClass("active"),n.removeClass("active"),s.addClass("active"),a.addClass("active"),e(document).trigger("settings.tabbed",s)})),e(".cld-field").not('[data-condition="false"]').each((function(){const t=e(this),n=t.data("condition");for(let i in n){const s=n[i],a=e("#field-"+i),r=t.closest("tr");a.on("change init",(function(){this.value===s||this.checked?r.show():r.hide()})),a.trigger("init")}})),e("#field-cloudinary_url").on("input change",(function(){let t=e(this),n=t.val();new RegExp(/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d)*[:]{1}[^:@]*[@]{1}[^@]*$/g).test(n)?(t.addClass("settings-valid-field"),t.removeClass("settings-invalid-field")):(t.removeClass("settings-valid-field"),t.addClass("settings-invalid-field"))})).trigger("change"),e('[name="cloudinary_sync_media[auto_sync]"]').change((function(){"on"===e(this).val()&&e("#auto-sync-alert-btn").click()}))})),e(".render-trigger[data-event]").each((function(){var t=e(this),n=t.data("event");t.trigger(n,this)}))}))}(window,jQuery)},function(e,t){if(wp.media&&window.CLDN){wp.media.events.on("editor:image-edit",(function(e){e.metadata.cldoverwrite=null,e.image.className.split(" ").indexOf("cld-overwrite")>=0&&(e.metadata.cldoverwrite="true")})),wp.media.events.on("editor:image-update",(function(e){let t=e.image.className.split(" ");e.metadata.cldoverwrite&&-1===t.indexOf("cld-overwrite")?t.push("cld-overwrite"):!e.metadata.cldoverwrite&&t.indexOf("cld-overwrite")>=0&&delete t[t.indexOf("cld-overwrite")],e.image.className=t.join(" ")}));let e=null,t=wp.media.string.props;wp.media.string.props=function(n,i){return n.cldoverwrite&&(n.classes=["cld-overwrite"],e=!0),t(n,i)},wp.media.post=function(t,n){if("send-attachment-to-editor"===t){let t=wp.media.editor.get().state().get("selection").get(n.attachment);t.attributes.transformations&&(n.attachment.transformations=t.attributes.transformations),(n.html.indexOf("cld-overwrite")>-1||!0===e)&&(n.attachment.cldoverwrite=!0,e=null)}return wp.ajax.post(t,n)};wp.media.controller.Library;let n=wp.media.view.MediaFrame.Select,i=wp.media.view.MediaFrame.Post,s=wp.media.view.MediaFrame.ImageDetails,a=wp.media.view.MediaFrame.VideoDetails,r=wp.media.View.extend({tagName:"div",className:"cloudinary-widget",template:wp.template("cloudinary-dam"),active:!1,toolbar:null,frame:null,ready:function(){let e=this.controller,t=this.model.get("selection"),n=this.model.get("library"),i=wp.media.model.Attachment;if(CLDN.mloptions.multiple=e.options.multiple,this.cid!==this.active){if(CLDN.mloptions.inline_container="#cloudinary-dam-"+e.cid,1===t.length){var s=i.get(t.models[0].id);void 0!==s.attributes.public_id&&(CLDN.mloptions.asset={resource_id:s.attributes.public_id})}else CLDN.mloptions.asset=null;window.ml=cloudinary.openMediaLibrary(CLDN.mloptions,{insertHandler:function(s){for(let a=0;a<s.assets.length;a++){let r=s.assets[a];wp.media.post("cloudinary-down-sync",{nonce:CLDN.nonce,asset:r}).done((function(s){let a=function(e,t){e.uploading=!1,t.set(e),wp.Uploader.queue.remove(t),0===wp.Uploader.queue.length&&wp.Uploader.queue.reset()};if(void 0!==s.resync&&s.resync.forEach((function(e){i.get(e.id).set(e)})),void 0!==s.fetch){let e=i.get(s.attachment_id);e.set(s),n.add(e),wp.Uploader.queue.add(e),wp.ajax.send({url:s.fetch,beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",CLDN.nonce)},data:{src:s.url,filename:s.filename,attachment_id:s.attachment_id,transformations:s.transformations}}).done((function(e){let t=i.get(e.id);a(e,t)})).fail((function(i){a(s,e),n.remove(e),t.remove(e),"string"==typeof i?alert(i):500===i.status&&alert("HTTP error.")}))}else{let e=i.get(s.id);e.set(s),t.add(e)}0===wp.Uploader.queue.length&&wp.Uploader.queue.reset(),e.content.mode("browse")}))}}},document.querySelectorAll(".dam-cloudinary")[0])}return this.active=this.cid,this}}),o=function(e){return{bindHandlers:function(){e.prototype.bindHandlers.apply(this,arguments),this.on("content:render:cloudinary",this.cloudinaryContent,this)},browseRouter:function(t){e.prototype.browseRouter.apply(this,arguments);this.state().get("id");t.set({cloudinary:{text:"Cloudinary",priority:60}})},cloudinaryContent:function(e){let t=this.state(),n=new r({controller:this,model:t}).render();this.content.set(n)}}};wp.media.view.MediaFrame.Select=n.extend(o(n)),wp.media.view.MediaFrame.Post=i.extend(o(i)),wp.media.view.MediaFrame.ImageDetails=s.extend(o(s)),wp.media.view.MediaFrame.VideoDetails=a.extend(o(a))}},function(e,t){!function(e,t){"use strict";var n,i,s={rootMargin:"256px 0px",threshold:.01,lazyImage:'img[loading="lazy"]',lazyIframe:'iframe[loading="lazy"]'},a="loading"in HTMLImageElement.prototype&&"loading"in HTMLIFrameElement.prototype,r="onscroll"in window;function o(e){var t,n,i=[];"picture"===e.parentNode.tagName.toLowerCase()&&((n=(t=e.parentNode).querySelector("source[data-lazy-remove]"))&&t.removeChild(n),i=Array.prototype.slice.call(e.parentNode.querySelectorAll("source"))),i.push(e),i.forEach((function(e){e.hasAttribute("data-lazy-srcset")&&(e.setAttribute("srcset",e.getAttribute("data-lazy-srcset")),e.removeAttribute("data-lazy-srcset"))})),e.setAttribute("src",e.getAttribute("data-lazy-src")),e.removeAttribute("data-lazy-src")}function d(e){var t=document.createElement("div");for(t.innerHTML=function(e){var t=e.textContent||e.innerHTML,i="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 "+((t.match(/width=['"](\d+)['"]/)||!1)[1]||1)+" "+((t.match(/height=['"](\d+)['"]/)||!1)[1]||1)+"%27%3E%3C/svg%3E";return!a&&r&&(void 0===n?t=t.replace(/(?:\r\n|\r|\n|\t| )src=/g,' lazyload="1" src='):("picture"===e.parentNode.tagName.toLowerCase()&&(t='<source srcset="'+i+'" data-lazy-remove="true"></source>'+t),t=t.replace(/(?:\r\n|\r|\n|\t| )srcset=/g," data-lazy-srcset=").replace(/(?:\r\n|\r|\n|\t| )src=/g,' src="'+i+'" data-lazy-src='))),t}(e);t.firstChild;)a||!r||void 0===n||!t.firstChild.tagName||"img"!==t.firstChild.tagName.toLowerCase()&&"iframe"!==t.firstChild.tagName.toLowerCase()||n.observe(t.firstChild),e.parentNode.insertBefore(t.firstChild,e);e.parentNode.removeChild(e)}function l(){document.querySelectorAll("noscript.loading-lazy").forEach(d),void 0!==window.matchMedia&&window.matchMedia("print").addListener((function(e){e.matches&&document.querySelectorAll(s.lazyImage+"[data-lazy-src],"+s.lazyIframe+"[data-lazy-src]").forEach((function(e){o(e)}))}))}"undefined"!=typeof NodeList&&NodeList.prototype&&!NodeList.prototype.forEach&&(NodeList.prototype.forEach=Array.prototype.forEach),"IntersectionObserver"in window&&(n=new IntersectionObserver((function(e,t){e.forEach((function(e){if(0!==e.intersectionRatio){var n=e.target;t.unobserve(n),o(n)}}))}),s)),i="requestAnimationFrame"in window?window.requestAnimationFrame:function(e){e()},/comp|inter/.test(document.readyState)?i(l):"addEventListener"in document?document.addEventListener("DOMContentLoaded",(function(){i(l)})):document.attachEvent("onreadystatechange",(function(){"complete"===document.readyState&&l()}))}()},function(e,t,n){},function(e,t,n){"use strict";n.r(t);n(2);var i=n(0),s=n.n(i);const a={progress:document.getElementById("progress-wrapper"),submitButton:document.getElementById("submit"),resyncButton:document.getElementById("resync"),stopButton:document.getElementById("stop-sync"),completed:document.getElementById("completed-notice"),show:"inline-block",hide:"none",isRunning:!1,getStatus:function(){var e=cloudinaryApi.restUrl+"cloudinary/v1/attachments";wp.ajax.send({url:e,type:"GET",beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){a.isRunning=e.is_running,a.isRunning&&setTimeout(a.getStatus,1e4),a._updateUI(e)}))},stopSync:function(){var e=cloudinaryApi.restUrl+"cloudinary/v1/sync";a.isRunning=!1,wp.ajax.send({url:e,data:{stop:!0},beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){a._updateUI(e)}))},pushAttachments:function(e){var t="resync"===e.currentTarget.id,n=cloudinaryApi.restUrl+"cloudinary/v1/sync"+(t?"?resync=1":"");a.isRunning=!0,a.progress.style.display=a.show,wp.ajax.send({url:n,beforeSend:function(e){e.setRequestHeader("X-WP-Nonce",cloudinaryApi.nonce)}}).done((function(e){setTimeout(a.getStatus,1e4)}))},_updateUI:function(e){e.percent<100&&void 0!==e.started?(this.submitButton.style.display=this.hide,this.stopButton.style.display=this.show):e.percent>=100&&void 0!==e.started?(this.submitButton.style.display=this.hide,this.stopButton.style.display=this.show):e.pending>0?(this.submitButton.style.display=this.show,this.stopButton.style.display=this.hide):e.processing>0?this.stopButton.style.display=this.show:this.stopButton.style.display=this.hide,100===e.percent&&(this.completed.style.display=this.show),this.isRunning?this.progress.style.display=this.show:this.progress.style.display=this.hide},_start:function(e){e.preventDefault(),a.stopButton.style.display=a.show,a.submitButton.style.display=a.hide,a.pushAttachments(e)},_reset:function(e){a.submitButton.style.display=a.hide,a.getStatus()},_init:function(e){"undefined"!=typeof cloudinaryApi&&((document.attachEvent?"complete"===document.readyState:"loading"!==document.readyState)?e():document.addEventListener("DOMContentLoaded",e))}};var r=a;[...document.getElementsByClassName("cld-deactivate")].forEach(e=>{e.addEventListener("click",(function(e){confirm(wp.i18n.__('Caution: Your storage setting is currently set to "Cloudinary only", disabling the plugin will result in broken links to media assets. Are you sure you want to continue?',"cloudinary"))||e.preventDefault()}))}),a._init((function(){a._reset(),a.submitButton.addEventListener("click",a._start),a.resyncButton.addEventListener("click",a._start),a.stopButton.addEventListener("click",a.stopSync)}));var o=n(1),d=n.n(o);const l={sample:{image:document.getElementById("transformation-sample-image"),video:document.getElementById("transformation-sample-video")},preview:{image:document.getElementById("sample-image"),video:document.getElementById("sample-video")},fields:document.getElementsByClassName("cld-field"),button:{image:document.getElementById("refresh-image-preview"),video:document.getElementById("refresh-video-preview")},spinner:{image:document.getElementById("image-loader"),video:document.getElementById("video-loader")},activeItem:null,elements:{image:[],video:[]},_placeItem:function(e){null!==e&&(e.style.display="block",e.style.visibility="visible",e.style.position="absolute",e.style.top=e.parentElement.clientHeight/2-e.clientHeight/2+"px",e.style.left=e.parentElement.clientWidth/2-e.clientWidth/2+"px")},_setLoading:function(e){this.button[e].style.display="block",this._placeItem(this.button[e]),this.preview[e].style.opacity="0.1"},_build:function(e){this.sample[e].innerHTML="",this.elements[e]=[];for(let t of this.fields){if(e!==t.dataset.context)continue;let n=t.value.trim();if(n.length){if("select-one"===t.type){if("none"===n)continue;n=t.dataset.meta+"_"+n}else{let e=t.dataset.context;n=this._transformations(n,e,!0)}n&&this.elements[e].push(n)}}let t="";this.elements[e].length&&(t="/"+this.elements[e].join(",").replace(/ /g,"%20")),this.sample[e].textContent=t,this.sample[e].parentElement.href="https://res.cloudinary.com/demo/"+this.sample[e].parentElement.innerText.trim().replace("../","").replace(/ /g,"%20")},_clearLoading:function(e){this.spinner[e].style.visibility="hidden",this.activeItem=null,this.preview[e].style.opacity=1},_refresh:function(e,t){e&&e.preventDefault();let n=this,i=CLD_GLOBAL_TRANSFORMATIONS[t].preview_url+n.elements[t].join(",")+CLD_GLOBAL_TRANSFORMATIONS[t].file;if(this.button[t].style.display="none",this._placeItem(this.spinner[t]),"image"===t){let e=new Image;e.onload=function(){n.preview[t].src=this.src,n._clearLoading(t),e.remove()},e.onerror=function(){alert(CLD_GLOBAL_TRANSFORMATIONS[t].error),n._clearLoading(t)},e.src=i}else{let e=n._transformations(n.elements[t].join(","),t);samplePlayer.source({publicId:"dog",transformation:e}),n._clearLoading(t)}},_transformations:function(e,t,n=!1){let i=CLD_GLOBAL_TRANSFORMATIONS[t].valid_types,s=null,a=e.split("/"),r=[];for(let e=0;e<a.length;e++){let s,o=a[e].split(",");s=!0===n?[]:{};for(let e=0;e<o.length;e++){let a=o[e].trim().split("_");if(a.length<=1||void 0===i[a[0]])continue;let r=a.shift(),d=a.join("_");if(!0===n){if("f"===r||"q"===r)for(let e in this.elements[t])r+"_"===this.elements[t][e].substr(0,2)&&this.elements[t].splice(e,1);s.push(o[e])}else s[i[r]]=d.trim()}let d=0;d=!0===n?s.length:Object.keys(s).length,d&&(!0===n&&(s=s.join(",")),r.push(s))}return r.length&&(s=!0===n?r.join("/").trim():r),s},_reset:function(){for(let e of this.fields)e.value=null;for(let e in this.button)this._build(e),this._refresh(null,e)},_input:function(e){if(void 0!==e.dataset.context&&e.dataset.context.length){let t=e.dataset.context;this._setLoading(t),this._build(t)}},_init:function(){if("undefined"!=typeof CLD_GLOBAL_TRANSFORMATIONS){let e=this;document.addEventListener("DOMContentLoaded",(function(t){for(let t in e.button)e.button[t]&&e.button[t].addEventListener("click",(function(n){e._refresh(n,t)}));for(let t of e.fields)t.addEventListener("input",(function(){e._input(this)})),t.addEventListener("change",(function(){e._input(this)}));for(let t in CLD_GLOBAL_TRANSFORMATIONS)e._build(t),e._refresh(null,t)})),jQuery(document).ajaxComplete((function(t,n,i){-1!==i.data.indexOf("action=add-tag")&&-1===n.responseText.indexOf("wp_error")&&e._reset()}))}}};l._init();var c=l;const u={template:"",tags:jQuery("#cld-tax-items"),tagDelimiter:window.tagsSuggestL10n&&window.tagsSuggestL10n.tagDelimiter||",",startId:null,_init:function(){if(!this.tags.length)return;const e=this;this._sortable(),"undefined"!=typeof wpAjax&&(wpAjax.procesParseAjaxResponse=wpAjax.parseAjaxResponse,wpAjax.parseAjaxResponse=function(t,n,i){let s=wpAjax.procesParseAjaxResponse(t,n,i);if(!s.errors&&s.responses[0]&&jQuery('[data-taxonomy="'+s.responses[0].what+'"]').length){const t=jQuery(s.responses[0].data).find("label").last().text().trim();e._pushItem(s.responses[0].what,t)}return s}),void 0!==window.tagBox&&(window.tagBox.processflushTags=window.tagBox.flushTags,window.tagBox.flushTags=function(t,n,i){if(void 0===i){let i,r;const o=t.prop("id"),d=jQuery("input.newtag",t);for(var s in i=(n=n||!1)?jQuery(n).text():d.val(),r=window.tagBox.clean(i).split(e.tagDelimiter),r){var a=o+":"+r[s];jQuery('[data-item="'+a+'"]').length||e._pushItem(a,r[s])}}return this.processflushTags(t,n,i)},window.tagBox.processTags=window.tagBox.parseTags,window.tagBox.parseTags=function(t){const n=t.id,i=n.split("-check-num-")[1],s=n.split("-check-num-")[0],a=jQuery(t).closest(".tagsdiv").find(".the-tags"),r=window.tagBox.clean(a.val()).split(e.tagDelimiter)[i];(new wp.api.collections.Tags).fetch({data:{slug:r}}).done(n=>{const i=!!n.length&&jQuery('[data-item="'+s+":"+n[0].id+'"]');i.length?i.remove():(jQuery(`.cld-tax-order-list-item:contains(${r})`).remove(),--e.startId),this.processTags(t)})}),jQuery("body").on("change",".selectit input",(function(){const t=jQuery(this),n=t.val(),i=t.is(":checked"),s=t.parent().text().trim();!0===i?e.tags.find(`[data-item="category:${n}"]`).length||e._pushItem(`category:${n}`,s):e.tags.find(`[data-item="category:${n}"]`).remove()}))},_createItem:function(e,t){const n=jQuery("<li/>"),i=jQuery("<span/>"),s=jQuery("<input/>");return n.addClass("cld-tax-order-list-item").attr("data-item",e),s.addClass("cld-tax-order-list-item-input").attr("type","hidden").attr("name","cld_tax_order[]").val(e),i.addClass("dashicons dashicons-menu cld-tax-order-list-item-handle"),n.append(i).append(t).append(s),n},_pushItem:function(e,t){let n=this._createItem(e,t);this.tags.append(n)},_sortable:function(){jQuery(".cld-tax-order-list").sortable({connectWith:".cld-tax-order",axis:"y",handle:".cld-tax-order-list-item-handle",placeholder:"cld-tax-order-list-item-placeholder",forcePlaceholderSize:!0,helper:"clone"})}};if(void 0!==window.CLDN&&(u._init(),jQuery("[data-wp-lists] .selectit input[checked]").map((e,t)=>{jQuery(t).trigger("change")})),wp.data&&wp.data.select("core/editor")){const e={};wp.data.subscribe((function(){let t=wp.data.select("core").getTaxonomies();if(t)for(let n in t){const i=wp.data.select("core/editor").getEditedPostAttribute(t[n].rest_base);e[t[n].slug]=i}}));const t=wp.element.createElement,n=n=>{class i extends n{constructor(e){super(e),this.currentItems=jQuery(".cld-tax-order-list-item").map((e,t)=>jQuery(t).data("item")).get()}makeItem(e){if(this.currentItems.includes(this.getId(e)))return;const t=this.makeElement(e);jQuery("#cld-tax-items").append(t)}removeItem(e){const t=jQuery(`[data-item="${this.getId(e)}"]`);t.length&&(t.remove(),this.currentItems=this.currentItems.filter(t=>t!==this.getId(e)))}findOrCreateTerm(e){return(e=super.findOrCreateTerm(e)).then(e=>this.makeItem(e)),e}onChange(t){super.onChange(t);const n=this.pickItem(t);n&&(e[this.props.slug].includes(n.id)?this.makeItem(n):this.removeItem(n))}pickItem(e){if("object"==typeof e){if(e.target){for(let t in this.state.availableTerms)if(this.state.availableTerms[t].id===parseInt(e.target.value))return this.state.availableTerms[t]}else if(Array.isArray(e)){let t=this.state.selectedTerms.filter(t=>!e.includes(t))[0];return void 0===t&&(t=e.filter(e=>!this.state.selectedTerms.includes(e))[0]),this.state.availableTerms.find(e=>e.name===t)}}else if("number"==typeof e){for(let t in this.state.availableTerms)if(this.state.availableTerms[t].id===e)return this.state.availableTerms[t]}else{let t;if(e.length>this.state.selectedTerms.length)for(let n in e)-1===this.state.selectedTerms.indexOf(e[n])&&(t=e[n]);else for(let n in this.state.selectedTerms)-1===e.indexOf(this.state.selectedTerms[n])&&(t=this.state.selectedTerms[n]);for(let e in this.state.availableTerms)if(this.state.availableTerms[e].name===t)return this.state.availableTerms[e]}}getId(e){return`${this.props.slug}:${e.id}`}makeElement(e){const t=jQuery("<li/>"),n=jQuery("<span/>"),i=jQuery("<input/>");return t.addClass("cld-tax-order-list-item").attr("data-item",this.getId(e)),i.addClass("cld-tax-order-list-item-input").attr("type","hidden").attr("name","cld_tax_order[]").val(this.getId(e)),n.addClass("dashicons dashicons-menu cld-tax-order-list-item-handle"),t.append(n).append(e.name).append(i),t}}return e=>t(i,e)};wp.hooks.addFilter("editor.PostTaxonomyType","cld",n)}var m=u;const p={wpWrap:document.getElementById("wpwrap"),wpContent:document.getElementById("wpbody-content"),libraryWrap:document.getElementById("cloudinary-embed"),_init:function(){let e=this;"undefined"!=typeof CLD_ML&&(cloudinary.openMediaLibrary(CLD_ML.mloptions,{insertHandler:function(e){alert("Import is not yet implemented.")}}),window.addEventListener("resize",(function(t){e._resize()})),e._resize())},_resize:function(){let e=getComputedStyle(this.wpContent);this.libraryWrap.style.height=this.wpWrap.offsetHeight-parseInt(e.getPropertyValue("padding-bottom"))+"px"}};var h=p;p._init();const f={_init:function(){let e=this;if("undefined"!=typeof CLDIS){[...document.getElementsByClassName("cld-notice")].forEach(t=>{t.addEventListener("click",n=>{"notice-dismiss"===n.target.className&&e._dismiss(t)})})}},_dismiss:function(e){let t=e.dataset.dismiss,n=e.dataset.duration;wp.ajax.send({url:CLDIS.url,data:{token:t,duration:n,_wpnonce:CLDIS.nonce}})}};window.addEventListener("load",f._init());var y=f;n(3);n.d(t,"cloudinary",(function(){return g}));window.$=window.jQuery;const g={settings:s.a,sync:r,widget:d.a,Global_Transformations:c,Terms_Order:m,Media_Library:h,Notices:y}}]);