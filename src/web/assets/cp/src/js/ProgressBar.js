/** global: Craft */
/** global: Garnish */
/**
 * File Manager.
 */
Craft.ProgressBar = Garnish.Base.extend(
  {
    $progressBar: null,
    $innerProgressBar: null,
    $progressBarStatus: null,

    intervalManager: null,

    _itemCount: 0,
    _progressPercentage: null,
    _processedItemCount: 0,
    _displaySteps: false,

    init: function ($element, displaySteps, settings) {
      this.setSettings(settings, Craft.ProgressBar.defaults);

      if (displaySteps) {
        this._displaySteps = true;
      }

      this.$progressBar = $(
        '<div class="progressbar pending hidden"/>'
      ).appendTo($element);
      this.$innerProgressBar = $('<div class="progressbar-inner"/>').appendTo(
        this.$progressBar
      );
      this.$progressBarStatus = $(
        '<div class="progressbar-status hidden" />'
      ).insertAfter(this.$progressBar);

      if (this.settings.announceProgress) {
        this.intervalManager = new Craft.IntervalManager({
          onInterval: () => {
            Craft.cp.announce(this.getProgressMessage.bind(this));
          },
        });
      }

      this.resetProgressBar();
    },

    /**
     * Reset the progress bar
     */
    resetProgressBar: function () {
      // Since setting the progress percentage implies that there is progress to be shown
      // It removes the pending class - we must add it back.
      this.setProgressPercentage(100);
      this.$progressBar.addClass('pending');

      // Reset all the counters
      this.setItemCount(1);
      this.setProcessedItemCount(0);
      this.$progressBarStatus.html('');

      if (this._displaySteps) {
        this.$progressBar.addClass('has-status');
      }
    },

    /**
     * Fade to invisible, hide it using a class and reset opacity to visible
     */
    hideProgressBar: function () {
      this.$progressBar.addClass('hidden');
      this.$progressBarStatus.addClass('hidden');

      if (this.settings.announceProgress) {
        this.intervalManager.stop();
      }
    },

    showProgressBar: function () {
      this.$progressBar.removeClass('hidden');
      this.$progressBarStatus.removeClass('hidden');

      if (this.settings.announceProgress) {
        this.intervalManager.start();
      }
    },

    getProgressMessage() {
      if (this._progressPercentage !== 100) {
        return Craft.t('app', '{num} percent complete', {
          num: this._progressPercentage,
        });
      } else {
        return Craft.t('app', 'Processing');
      }
    },

    setItemCount: function (count) {
      this._itemCount = count;
    },

    incrementItemCount: function (count) {
      this._itemCount += count;
    },

    setProcessedItemCount: function (count) {
      this._processedItemCount = count;
    },

    incrementProcessedItemCount: function (count) {
      this._processedItemCount += count;
    },

    updateProgressBar: function () {
      // Only fools would allow accidental division by zero.
      this._itemCount = Math.max(this._itemCount, 1);

      var width = Math.min(
        100,
        Math.round((100 * this._processedItemCount) / this._itemCount)
      );

      this.setProgressPercentage(width);

      if (this._displaySteps) {
        this.$progressBarStatus.html(
          this._processedItemCount + ' / ' + this._itemCount
        );
      }
    },

    setProgressPercentage: function (percentage, animate) {
      this._progressPercentage = percentage;

      if (percentage === 0) {
        this.$progressBar.addClass('pending');
      } else {
        this.$progressBar.removeClass('pending');

        if (animate) {
          this.$innerProgressBar
            .velocity('stop')
            .velocity({width: percentage + '%'}, 'fast');
        } else {
          this.$innerProgressBar.velocity('stop').width(percentage + '%');
        }
      }
    },
  },
  {
    defaults: {
      announceProgress: true,
    },
  }
);
