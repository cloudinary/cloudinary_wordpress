!function(e){var t={};function i(n){if(t[n])return t[n].exports;var r=t[n]={i:n,l:!1,exports:{}};return e[n].call(r.exports,r,r.exports,i),r.l=!0,r.exports}i.m=e,i.c=t,i.d=function(e,t,n){i.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:n})},i.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},i.t=function(e,t){if(1&t&&(e=i(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(i.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var r in e)i.d(n,r,function(t){return e[t]}.bind(null,r));return n},i.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return i.d(t,"a",t),t},i.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},i.p="",i(i.s=0)}([function(e,t){var i={deviceDensity:window.devicePixelRatio?window.devicePixelRatio:"auto",density:null,config:CLDLB||{},lazyThreshold:0,enabled:!1,sizeBands:[],iObserver:null,pObserver:null,rObserver:null,aboveFold:!0,bind:function(e){var t=this;e.CLDbound=!0,this.enabled||this._init();var i=e.dataset.size.split(" ");e.originalWidth=i[0],e.originalHeight=i[1],this.pObserver?(this.aboveFold&&this.inInitialView(e)?this.buildImage(e):(this.pObserver.observe(e),this.iObserver.observe(e)),e.addEventListener("error",(function(i){e.srcset="",e.src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>',t.rObserver.unobserve(e)}))):this.setupFallback(e)},buildImage:function(e){e.dataset.srcset?(e.cld_loaded=!0,e.srcset=e.dataset.srcset):(e.src=this.getSizeURL(e),e.dataset.responsive&&this.rObserver.observe(e))},inInitialView:function(e){var t=e.getBoundingClientRect();return this.aboveFold=t.top<window.innerHeight+this.lazyThreshold,this.aboveFold},setupFallback:function(e){var t=this,i=[];this.sizeBands.forEach((function(n){if(n<=e.originalWidth){var r=t.getSizeURL(e,n,!0)+" ".concat(n,"w");-1===i.indexOf(r)&&i.push(r)}})),e.srcset=i.join(","),e.sizes="(max-width: ".concat(e.originalWidth,"px) 100vw, ").concat(e.originalWidth,"px")},_init:function(){this.enabled=!0,this._calcThreshold(),this._getDensity();for(var e=parseInt(this.config.max_width),t=parseInt(this.config.min_width),i=parseInt(this.config.pixel_step);e-i>=t;)e-=i,this.sizeBands.push(e);"undefined"!=typeof IntersectionObserver&&this._setupObservers(),this.enabled=!0},_setupObservers:function(){var e=this,t={rootMargin:this.lazyThreshold+"px 0px "+this.lazyThreshold+"px 0px"},i={rootMargin:2*this.lazyThreshold+"px 0px "+2*this.lazyThreshold+"px 0px"};this.rObserver=new ResizeObserver((function(t,i){t.forEach((function(t){t.target.cld_loaded&&t.contentRect.width>=t.target.cld_loaded&&(t.target.src=e.getSizeURL(t.target))}))})),this.iObserver=new IntersectionObserver((function(t,i){t.forEach((function(t){t.isIntersecting&&(e.buildImage(t.target),i.unobserve(t.target))}))}),t),this.pObserver=new IntersectionObserver((function(t,i){t.forEach((function(t){t.isIntersecting&&(t.intersectionRatio<.5&&(t.target.src=e.getPlaceholderURL(t.target)),i.unobserve(t.target))}))}),i)},_calcThreshold:function(){var e=this.config.lazy_threshold.replace(/[^0-9]/g,""),t=0;switch(this.config.lazy_threshold.replace(/[0-9]/g,"").toLowerCase()){case"em":t=parseFloat(getComputedStyle(document.body).fontSize)*e;break;case"rem":t=parseFloat(getComputedStyle(document.documentElement).fontSize)*e;break;case"vh":t=window.innerHeight/e*100;break;default:t=e}this.lazyThreshold=parseInt(t,10)},_getDensity:function(){var e=this.config.dpr?this.config.dpr.replace("X",""):"off";if("off"===e)return this.density=1,1;var t=this.deviceDensity;"max"!==e&&"auto"!==t&&(e=parseFloat(e),t=t>Math.ceil(e)?e:t),this.density=t},scaleWidth:function(e,t){var i=parseInt(this.config.max_width);if(!t)for(t=e.width;-1===this.sizeBands.indexOf(t)&&t<i;)t++;return t>i&&(t=i),e.originalWidth<t&&(t=e.originalWidth),t},scaleSize:function(e,t,i){var n=(e.originalWidth/e.originalHeight).toFixed(3),r=(e.width/e.height).toFixed(3),s=this.scaleWidth(e,t),o=[];e.width!==e.originalWidth&&o.push(n===r?"c_scale":"c_fill,g_auto");var a=Math.round(s/r);return o.push("w_"+s),o.push("h_"+a),i&&1!==this.density&&o.push("dpr_"+this.density),e.cld_loaded=s,{transformation:o.join(","),nameExtension:s+"x"+a}},getSizeURL:function(e,t){var i=this.scaleSize(e,t,!0);return[this.config.base_url,"image",e.dataset.delivery,"upload"===e.dataset.delivery?i.transformation:"",e.dataset.transformations,"v"+e.dataset.version,e.dataset.publicId+"?_i=AA"].filter(this.empty).join("/")},getPlaceholderURL:function(e){return e.cld_placehold=!0,this.scaleSize(e,null,!1),[this.config.base_url,"image",e.dataset.delivery,this.config.placeholder,e.dataset.publicId].filter(this.empty).join("/")},empty:function(e){return void 0!==e&&0!==e.length}};window.CLDBind=function(e){e.CLDbound||i.bind(e)}}]);