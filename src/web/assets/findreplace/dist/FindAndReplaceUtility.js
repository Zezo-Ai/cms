!function(){var s;s=jQuery,Craft.FindAndReplaceUtility=Garnish.Base.extend({$trigger:null,$form:null,init:function(t){this.$form=s("#"+t),this.$trigger=s("input.submit",this.$form),this.$status=s(".utility-status",this.$form),this.addListener(this.$form,"submit","onSubmit")},onSubmit:function(s){var t=this;s.preventDefault(),this.$trigger.hasClass("disabled")||(this.progressBar?this.progressBar.resetProgressBar():this.progressBar=new Craft.ProgressBar(this.$status),this.progressBar.showProgressBar(),this.progressBar.$progressBar.velocity("stop").velocity({opacity:1},{complete:function(){var s=Garnish.getPostData(t.$form),r=Craft.expandPostArray(s),e={params:r};Craft.sendActionRequest("POST",r.action,{data:e}).then((function(s){t.updateProgressBar(),setTimeout(t.onComplete.bind(t),300)})).catch((function(s){var t=s.response;Craft.cp.displayError(t.data.message)}))}}),this.$allDone&&this.$allDone.css("opacity",0),this.$trigger.addClass("disabled"),this.$trigger.trigger("blur"))},updateProgressBar:function(){this.progressBar.setProgressPercentage(100)},onComplete:function(){this.$allDone||(this.$allDone=s('<div class="alldone" data-icon="done" />').appendTo(this.$status),this.$allDone.css("opacity",0)),this.progressBar.hideProgressBar(),this.$allDone.velocity({opacity:1},{duration:"fast"}),Craft.cp.announce(Craft.t("app","Success")),this.$trigger.removeClass("disabled"),this.$trigger.focus(),Craft.cp.trackJobProgress(!1,!0)}})}();
//# sourceMappingURL=FindAndReplaceUtility.js.map