!function(){"use strict";function t(t,e){(null==e||e>t.length)&&(e=t.length);for(var n=0,o=Array(e);n<e;n++)o[n]=t[n];return o}function e(e){return function(e){if(Array.isArray(e))return t(e)}(e)||function(t){if("undefined"!=typeof Symbol&&null!=t[Symbol.iterator]||null!=t["@@iterator"])return Array.from(t)}(e)||function(e,n){if(e){if("string"==typeof e)return t(e,n);var o={}.toString.call(e).slice(8,-1);return"Object"===o&&e.constructor&&(o=e.constructor.name),"Map"===o||"Set"===o?Array.from(e):"Arguments"===o||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(o)?t(e,n):void 0}}(e)||function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}var n={modal:document.getElementById("cloudinary-deactivation"),modalBody:document.getElementById("modal-body"),modalFooter:document.getElementById("modal-footer"),modalUninstall:document.getElementById("modal-uninstall"),modalClose:document.querySelectorAll('button[data-action="cancel"], button[data-action="close"]'),pluginListLinks:document.querySelectorAll(".cld-deactivate-link, .cld-deactivate"),triggers:document.getElementsByClassName("cld-deactivate"),options:document.querySelectorAll('.cloudinary-deactivation .reasons input[type="radio"]'),report:document.getElementById("cld-report"),contact:document.getElementById("cld-contact"),submitButton:document.querySelectorAll('.cloudinary-deactivation button[data-action="submit"]'),contactButton:document.querySelectorAll('.cloudinary-deactivation button[data-action="contact"]'),deactivateButton:document.querySelectorAll('.cloudinary-deactivation button[data-action="deactivate"]'),emailField:document.getElementById("email"),reason:"",more:null,deactivationUrl:"",email:"",isCloudinaryOnly:!1,addEvents:function(){var t=this;if(e(t.modalClose).forEach((function(e){e.addEventListener("click",(function(e){t.closeModal()}))})),window.addEventListener("keyup",(function(e){"visible"===t.modal.style.visibility&&"Escape"===e.key&&(t.modal.style.visibility="hidden",t.modal.style.opacity="0")})),t.modal.addEventListener("click",(function(e){e.stopPropagation(),e.target===t.modal&&t.closeModal()})),e(t.pluginListLinks).forEach((function(e){e.addEventListener("click",(function(e){e.preventDefault(),t.deactivationUrl=e.target.getAttribute("href"),t.openModal()}))})),e(t.contactButton).forEach((function(e){e.addEventListener("click",(function(){t.emailField&&(t.email=t.emailField.value),t.submit()}))})),e(t.deactivateButton).forEach((function(e){e.addEventListener("click",(function(){window.location.href=t.deactivationUrl}))})),e(t.options).forEach((function(e){e.addEventListener("change",(function(e){t.reason=e.target.value,t.more=e.target.parentNode.querySelector("textarea")}))})),t.contact&&t.report.addEventListener("change",(function(){t.report.checked?t.contact.parentNode.removeAttribute("style"):t.contact.parentNode.style.display="none"})),e(t.submitButton).forEach((function(e){e.addEventListener("click",(function(){var e=document.querySelector('.cloudinary-deactivation .data input[name="option"]:checked'),n="";e&&(n=e.value),"uninstall"===n&&(t.modalBody.style.display="none",t.modalFooter.style.display="none",t.modalUninstall.style.display="block"),t.submit(n)}))})),this.isCloudinaryOnly){var n=document.getElementById("cld-bypass-cloudinary-only");n.addEventListener("change",function(t){this.modal.dataset.cloudinaryOnly=!n.checked}.bind(this))}},closeModal:function(){document.body.style.removeProperty("overflow"),this.modal.style.visibility="hidden",this.modal.style.opacity="0"},openModal:function(){document.body.style.overflow="hidden",this.modal.style.visibility="visible",this.modal.style.opacity="1"},submit:function(){var t,e,n,o=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"";wp.ajax.send({url:CLD_Deactivate.endpoint,data:{reason:this.reason,more:null===(t=this.more)||void 0===t?void 0:t.value,report:null===(e=this.report)||void 0===e?void 0:e.checked,contact:null===(n=this.contact)||void 0===n?void 0:n.checked,email:this.email,dataHandling:o},beforeSend:function(t){t.setRequestHeader("X-WP-Nonce",CLD_Deactivate.nonce)}}).always((function(){window.location.reload()}))},init:function(){this.isCloudinaryOnly=!!this.modal.dataset.cloudinaryOnly,this.addEvents()}};n.init()}();