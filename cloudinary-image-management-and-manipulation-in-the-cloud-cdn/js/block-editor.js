!function(t){var e={};function r(n){if(e[n])return e[n].exports;var o=e[n]={i:n,l:!1,exports:{}};return t[n].call(o.exports,o,o.exports,r),o.l=!0,o.exports}r.m=t,r.c=e,r.d=function(t,e,n){r.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:n})},r.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},r.t=function(t,e){if(1&e&&(t=r(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var n=Object.create(null);if(r.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var o in t)r.d(n,o,function(e){return t[e]}.bind(null,o));return n},r.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return r.d(e,"a",e),e},r.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},r.p="",r(r.s=4)}([function(t,e){!function(){t.exports=this.wp.i18n}()},function(t,e){!function(){t.exports=this.wp.components}()},function(t,e){!function(){t.exports=this.wp.data}()},function(t,e){!function(){t.exports=this.wp.element}()},function(t,e,r){"use strict";r.r(e);var n=r(0),o=r(2),i=(r(3),r(1));function a(t,e){var r=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);e&&(n=n.filter((function(e){return Object.getOwnPropertyDescriptor(t,e).enumerable}))),r.push.apply(r,n)}return r}function c(t){for(var e=1;e<arguments.length;e++){var r=null!=arguments[e]?arguments[e]:{};e%2?a(Object(r),!0).forEach((function(e){u(t,e,r[e])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(r)):a(Object(r)).forEach((function(e){Object.defineProperty(t,e,Object.getOwnPropertyDescriptor(r,e))}))}return t}function u(t,e,r){return e in t?Object.defineProperty(t,e,{value:r,enumerable:!0,configurable:!0,writable:!0}):t[e]=r,t}var l={_init:function(){"undefined"!=typeof CLD_VIDEO_PLAYER&&wp.hooks.addFilter("blocks.registerBlockType","Cloudinary/Media/Video",(function(t,e){return"core/video"===e&&("off"!==CLD_VIDEO_PLAYER.video_autoplay_mode&&(t.attributes.autoplay.default=!0),"on"===CLD_VIDEO_PLAYER.video_loop&&(t.attributes.loop.default=!0),"off"===CLD_VIDEO_PLAYER.video_controls&&(t.attributes.controls.default=!1)),t}))}},f=l;l._init();wp.hooks.addFilter("blocks.registerBlockType","cloudinary/addAttributes",(function(t,e){return"core/image"!==e&&"core/video"!==e||(t.attributes||(t.attributes={}),t.attributes.overwrite_transformations={type:"boolean"},t.attributes.transformations={type:"boolean"}),t}));var s=function(t){var e=t.attributes,r=e.overwrite_transformations,o=(e.transformations,t.setAttributes);return(React.createElement(i.PanelBody,{title:Object(n.__)("Transformations","cloudinary")},React.createElement(i.ToggleControl,{label:Object(n.__)("Overwrite Transformations","cloudinary"),checked:r,onChange:function(t){o({overwrite_transformations:t})}})))},d=function(t){var e=t.setAttributes,r=t.media,n=wp.editor.InspectorControls;return r&&r.transformations&&e({transformations:!0}),React.createElement(n,null,React.createElement(s,t))};d=Object(o.withSelect)((function(t,e){return c(c({},e),{},{media:e.attributes.id?t("core").getMedia(e.attributes.id):null})}))(d);wp.hooks.addFilter("editor.BlockEdit","cloudinary/filterEdit",(function(t){return function(e){var r=e.name,n="core/image"===r||"core/video"===r;return React.createElement(React.Fragment,null,n?React.createElement(d,e):null,React.createElement(t,e))}}),20),r.d(e,"cloudinaryBlocks",(function(){return p}));window.$=window.jQuery;var p={Video:f}}]);