!function(e){var t={};function r(o){if(t[o])return t[o].exports;var n=t[o]={i:o,l:!1,exports:{}};return e[o].call(n.exports,n,n.exports,r),n.l=!0,n.exports}r.m=e,r.c=t,r.d=function(e,t,o){r.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:o})},r.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},r.t=function(e,t){if(1&t&&(e=r(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var o=Object.create(null);if(r.r(o),Object.defineProperty(o,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var n in e)r.d(o,n,function(t){return e[t]}.bind(null,n));return o},r.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return r.d(t,"a",t),t},r.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},r.p="",r(r.s=162)}({0:function(e,t){e.exports=window.React},1:function(e,t){e.exports=window.wp.i18n},12:function(e,t){e.exports=function(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e},e.exports.default=e.exports,e.exports.__esModule=!0},14:function(e,t,r){var o=r(9);e.exports=function(e,t){if(e){if("string"==typeof e)return o(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?o(e,t):void 0}},e.exports.default=e.exports,e.exports.__esModule=!0},141:function(e,t){e.exports=function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")},e.exports.default=e.exports,e.exports.__esModule=!0},142:function(e,t){function r(e,t){for(var r=0;r<t.length;r++){var o=t[r];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(e,o.key,o)}}e.exports=function(e,t,o){return t&&r(e.prototype,t),o&&r(e,o),e},e.exports.default=e.exports,e.exports.__esModule=!0},143:function(e,t,r){var o=r(153);function n(t,r,i){return"undefined"!=typeof Reflect&&Reflect.get?(e.exports=n=Reflect.get,e.exports.default=e.exports,e.exports.__esModule=!0):(e.exports=n=function(e,t,r){var n=o(e,t);if(n){var i=Object.getOwnPropertyDescriptor(n,t);return i.get?i.get.call(r):i.value}},e.exports.default=e.exports,e.exports.__esModule=!0),n(t,r,i||t)}e.exports=n,e.exports.default=e.exports,e.exports.__esModule=!0},144:function(e,t,r){var o=r(154);e.exports=function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&o(e,t)},e.exports.default=e.exports,e.exports.__esModule=!0},145:function(e,t,r){var o=r(7).default,n=r(155);e.exports=function(e,t){return!t||"object"!==o(t)&&"function"!=typeof t?n(e):t},e.exports.default=e.exports,e.exports.__esModule=!0},153:function(e,t,r){var o=r(61);e.exports=function(e,t){for(;!Object.prototype.hasOwnProperty.call(e,t)&&null!==(e=o(e)););return e},e.exports.default=e.exports,e.exports.__esModule=!0},154:function(e,t){function r(t,o){return e.exports=r=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e},e.exports.default=e.exports,e.exports.__esModule=!0,r(t,o)}e.exports=r,e.exports.default=e.exports,e.exports.__esModule=!0},155:function(e,t){e.exports=function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e},e.exports.default=e.exports,e.exports.__esModule=!0},162:function(e,t,r){"use strict";r.r(t),r.d(t,"cloudinaryBlocks",(function(){return V}));var o=r(12),n=r.n(o),i=(r(4),r(0)),a=r.n(i),u=r(1),c=r(6),l=r(2);function s(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(e);t&&(o=o.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,o)}return r}function f(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?s(Object(r),!0).forEach((function(t){n()(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):s(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}var p={_init:function(){"undefined"!=typeof CLD_VIDEO_PLAYER&&wp.hooks.addFilter("blocks.registerBlockType","Cloudinary/Media/Video",(function(e,t){return"core/video"===t&&("off"!==CLD_VIDEO_PLAYER.video_autoplay_mode&&(e.attributes.autoplay.default=!0),"on"===CLD_VIDEO_PLAYER.video_loop&&(e.attributes.loop.default=!0),"off"===CLD_VIDEO_PLAYER.video_controls&&(e.attributes.controls.default=!1)),e}))}},d=p;p._init();wp.hooks.addFilter("blocks.registerBlockType","cloudinary/addAttributes",(function(e,t){return"core/image"!==t&&"core/video"!==t||(e.attributes||(e.attributes={}),e.attributes.overwrite_transformations={type:"boolean"},e.attributes.transformations={type:"boolean"}),e}));var y=function(e){var t=e.attributes.overwrite_transformations,r=e.setAttributes;return a.a.createElement(l.PanelBody,{title:Object(u.__)("Transformations","cloudinary")},a.a.createElement(l.ToggleControl,{label:Object(u.__)("Overwrite Global Transformations","cloudinary"),checked:t,onChange:function(e){r({overwrite_transformations:e})}}))},b=function(e){var t=e.setAttributes,r=e.media,o=wp.editor.InspectorControls;return r&&r.transformations&&t({transformations:!0}),a.a.createElement(o,null,a.a.createElement(y,e))};b=Object(c.withSelect)((function(e,t){return f(f({},t),{},{media:t.attributes.id?e("core").getMedia(t.attributes.id):null})}))(b);wp.hooks.addFilter("editor.BlockEdit","cloudinary/filterEdit",(function(e){return function(t){var r=t.name,o="core/image"===r||"core/video"===r;return a.a.createElement(a.a.Fragment,null,o?a.a.createElement(b,t):null,a.a.createElement(e,t))}}),20);var x=r(141),m=r.n(x),v=r(142),_=r.n(v),h=r(143),w=r.n(h),g=r(144),O=r.n(g),j=r(145),E=r.n(j),M=r(61),P=r.n(M);function S(e){var t=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch(e){return!1}}();return function(){var r,o=P()(e);if(t){var n=P()(this).constructor;r=Reflect.construct(o,arguments,n)}else r=o.apply(this,arguments);return E()(this,r)}}var A=function(e){return a.a.createElement(a.a.Fragment,null,e.modalClass&&a.a.createElement(l.ToggleControl,{label:Object(u.__)("Overwrite Transformations","cloudinary"),checked:e.overwrite_featured_transformations,onChange:function(t){return e.setOverwrite(t)}}))};A=Object(c.withSelect)((function(e){var t,r;return{overwrite_featured_transformations:null!==(t=null===(r=e("core/editor"))||void 0===r?void 0:r.getEditedPostAttribute("meta")._cloudinary_featured_overwrite)&&void 0!==t&&t}}))(A),A=Object(c.withDispatch)((function(e){return{setOverwrite:function(t){e("core/editor").editPost({meta:{_cloudinary_featured_overwrite:t}})}}}))(A);var C=function(e){return function(e){O()(r,e);var t=S(r);function r(){return m()(this,r),t.apply(this,arguments)}return _()(r,[{key:"render",value:function(){return a.a.createElement(a.a.Fragment,null,w()(P()(r.prototype),"render",this).call(this),!!this.props.value&&a.a.createElement(A,this.props))}}]),r}(e)},R={_init:function(){wp.hooks.addFilter("editor.MediaUpload","cloudinary/filter-featured-image",C)}};R._init();var T=R,k=r(8),I=r.n(k);function D(e,t){var r="undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(!r){if(Array.isArray(e)||(r=function(e,t){if(!e)return;if("string"==typeof e)return L(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);"Object"===r&&e.constructor&&(r=e.constructor.name);if("Map"===r||"Set"===r)return Array.from(e);if("Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r))return L(e,t)}(e))||t&&e&&"number"==typeof e.length){r&&(e=r);var o=0,n=function(){};return{s:n,n:function(){return o>=e.length?{done:!0}:{done:!1,value:e[o++]}},e:function(e){throw e},f:n}}throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}var i,a=!0,u=!1;return{s:function(){r=r.call(e)},n:function(){var e=r.next();return a=e.done,e},e:function(e){u=!0,i=e},f:function(){try{a||null==r.return||r.return()}finally{if(u)throw i}}}}function L(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,o=new Array(t);r<t;r++)o[r]=e[r];return o}var B={wrapper:null,query:{per_page:-1,orderby:"name",order:"asc",_fields:"id,name,parent",context:"view"},available:{},_init:function(){var e=this;this.wrapper=document.getElementById("cld-tax-items"),setTimeout((function(){e._init_listeners()}),3e3)},_init_listeners:function(){var e=this;Object(c.select)("core").getTaxonomies().forEach((function(t){t.rest_base&&t.visibility.public&&Object(c.subscribe)((function(){var r=t.slug,o=t.hierarchical,n=Object(c.select)("core/data").isResolving,i=["taxonomy",r,e.query];e.available[r]=null,o&&(e.available[r]=Object(c.select)("core").getEntityRecords("taxonomy",r,e.query)),n("core","getEntityRecords",i)||e.event(t)}))}))},event:function(e){var t=this,r=Object(c.select)("core/editor").getEditedPostAttribute(e.rest_base);if(r){var o=I()(r),n=Array.from(this.wrapper.querySelectorAll('[data-item*="'.concat(e.slug,'"]')));I()(o).forEach((function(r){var o=t.wrapper.querySelector('[data-item="'.concat(e.slug,":").concat(r,'"]'));n.splice(n.indexOf(o),1),null===o&&t.createItem(t.getItem(e,r))})),n.forEach((function(e){e.parentNode.removeChild(e)}))}},createItem:function(e){if(e&&e.id){var t=document.createElement("li"),r=document.createElement("span"),o=document.createElement("input"),n=document.createTextNode(e.name);t.classList.add("cld-tax-order-list-item"),t.dataset.item="".concat(e.taxonomy,":").concat(e.id),o.classList.add("cld-tax-order-list-item-input"),o.type="hidden",o.name="cld_tax_order[]",o.value="".concat(e.taxonomy,":").concat(e.id),r.className="dashicons dashicons-menu cld-tax-order-list-item-handle",t.appendChild(r),t.appendChild(o),t.appendChild(n),this.wrapper.appendChild(t)}},getItem:function(e,t){var r={};if(null===this.available[e.slug])r=Object(c.select)("core").getEntityRecord("taxonomy",e.slug,t);else{var o,n=D(this.available[e.slug]);try{for(n.s();!(o=n.n()).done;){var i=o.value;if(i.id===t){(r=i).taxonomy=e.slug;break}}}catch(e){n.e(e)}finally{n.f()}}return r}};window.addEventListener("load",(function(){return B._init()}));var F=B;window.$=window.jQuery;var V={Video:d,Featured:T,Terms:F}},2:function(e,t){e.exports=window.wp.components},26:function(e,t,r){var o=r(9);e.exports=function(e){if(Array.isArray(e))return o(e)},e.exports.default=e.exports,e.exports.__esModule=!0},27:function(e,t){e.exports=function(e){if("undefined"!=typeof Symbol&&null!=e[Symbol.iterator]||null!=e["@@iterator"])return Array.from(e)},e.exports.default=e.exports,e.exports.__esModule=!0},28:function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")},e.exports.default=e.exports,e.exports.__esModule=!0},4:function(e,t){e.exports=window.wp.element},6:function(e,t){e.exports=window.wp.data},61:function(e,t){function r(t){return e.exports=r=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)},e.exports.default=e.exports,e.exports.__esModule=!0,r(t)}e.exports=r,e.exports.default=e.exports,e.exports.__esModule=!0},7:function(e,t){function r(t){return"function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?(e.exports=r=function(e){return typeof e},e.exports.default=e.exports,e.exports.__esModule=!0):(e.exports=r=function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},e.exports.default=e.exports,e.exports.__esModule=!0),r(t)}e.exports=r,e.exports.default=e.exports,e.exports.__esModule=!0},8:function(e,t,r){var o=r(26),n=r(27),i=r(14),a=r(28);e.exports=function(e){return o(e)||n(e)||i(e)||a()},e.exports.default=e.exports,e.exports.__esModule=!0},9:function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,o=new Array(t);r<t;r++)o[r]=e[r];return o},e.exports.default=e.exports,e.exports.__esModule=!0}});