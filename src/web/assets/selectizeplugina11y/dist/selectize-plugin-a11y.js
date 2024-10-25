Selectize.define("selectize-plugin-a11y",(function(t){var i,e=this;void 0===e.accessibility&&(e.accessibility={}),e.accessibility.helpers={randomId:function(t){for(var i="",e=t||10,a=0;a<e;a++)i+="abcdefghijklmnopqrstuvwxyz0123456789"[Math.floor(36*Math.random())];return i}},e.accessibility.liveRegion={$region:"",speak:function(t){var i=$("<div></div>");i.text(t),this.$region.html(i)},domListener:function(){var t=new MutationObserver((function(t){t.forEach((function(t){var i=$(t.target);i.hasClass("items")?i.hasClass("dropdown-active")?e.$control_input.attr("aria-expanded","true"):(e.$control_input.attr("aria-expanded","false"),e.$control_input.removeAttr("aria-activedescendant")):i.hasClass("active")&&i.attr("data-value")&&(e.$control_input.attr("aria-activedescendant",i.attr("id")),e.accessibility.liveRegion.speak(i.text(),500))}))}));t.observe(e.$dropdown[0],{attributes:!0,attributeFilter:["class"],subtree:!0,attributeOldValue:!0}),t.observe(e.$control[0],{attributes:!0,attributeFilter:["class"]}),t.observe(e.$control_input[0],{attributes:!0,attributeFilter:["value"]})},setAttributes:function(){this.$region.attr({"aria-live":"assertive",role:"log","aria-relevant":"additions","aria-atomic":"true"})},setStyles:function(){this.$region.css({position:"absolute",width:"1px",height:"1px","margin-top":"-1px",clip:"rect(1px, 1px, 1px, 1px)",overflow:"hidden"})},init:function(){this.$region=$("<div>"),this.setAttributes(),this.setStyles(),$("body").append(this.$region),this.domListener()}},this.setup=(i=e.setup,function(){i.apply(this,arguments),e.accessibility.helpers.randomId();var t=e.accessibility.helpers.randomId();e.$control.on("keydown",(function(t){13===t.keyCode&&$(this).click()})),e.$control_input.attr({role:"combobox","aria-expanded":"false",haspopup:"listbox","aria-owns":t,"aria-label":e.$wrapper.closest("[data-accessibility-selectize-label]").attr("data-accessibility-selectize-label")}),e.$dropdown_content.attr({role:"listbox",id:t}),e.accessibility.liveRegion.init()}),this.destroy=function(){var t=e.destroy;return function(){return e.accessibility.liveRegion.$region.remove(),t.apply(this,arguments)}}()}));