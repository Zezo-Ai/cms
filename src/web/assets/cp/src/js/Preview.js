/** global: Craft */
/** global: Garnish */
/**
 * Preview
 */
Craft.Preview = Garnish.Base.extend(
  {
    elementEditor: null,
    tabManager: null,
    hasTabs: false,

    $shade: null,

    editorId: null,
    $editorContainer: null,
    $editorHeader: null,
    $editorToolbar: null,
    $tabContainer: null,
    $content: null,
    $spinner: null,

    $editorFooter: null,
    $saveBtn: null,

    $dragHandle: null,
    $previewWrapper: null,
    $previewContainer: null,
    $previewSkipLink: null,
    $bumperLink: null,
    $notifier: null,
    $iframeContainer: null,
    $previewHeader: null,
    $targetBtn: null,
    $targetMenu: null,
    $deviceTypeContainer: null,
    $orientationBtn: null,
    $refreshBtn: null,
    $deviceMask: null,
    $devicePreviewContainer: null,
    $iframe: null,
    iframeLoaded: false,

    isActive: false,
    isVisible: false,
    activeTarget: 0,

    animationDuration: 'slow',

    currentDeviceType: 'desktop',
    deviceOrientation: null,
    deviceWidth: '',
    deviceHeight: '',
    deviceMaskDimensions: {
      phone: {
        width: 375,
        height: 753,
      },
      tablet: {
        width: 768,
        height: 1110,
      },
    },

    draftId: null,
    url: null,

    iframeHeight: null,
    scrollTop: null,
    scrollLeft: null,

    dragger: null,
    dragStartEditorWidth: null,

    _editorWidth: null,
    _editorWidthInPx: null,

    /**
     * @param {Object} [settings]
     */
    init: function (settings) {
      this.setSettings(settings, Craft.Preview.defaults);

      // Set the initial editor width
      this.editorWidth = Craft.getLocalStorage(
        'LivePreview.editorWidth',
        Craft.Preview.defaultEditorWidth
      );

      Craft.Preview.instances.push(this);

      if (this.settings.standaloneMode) {
        this.open(false);
      }
    },

    get editorWidth() {
      return this._editorWidth;
    },

    get editorWidthInPx() {
      return this._editorWidthInPx;
    },

    set editorWidth(width) {
      let inPx;

      // Is this getting set in pixels?
      if (width >= 1) {
        inPx = width;
        width /= Garnish.$win.width();
      } else {
        inPx = Math.round(width * Garnish.$win.width());
      }

      // Make sure it's no less than the minimum
      if (inPx < Craft.Preview.minEditorWidthInPx) {
        inPx = Craft.Preview.minEditorWidthInPx;
        width = inPx / Garnish.$win.width();
      }

      this._editorWidth = width;
      this._editorWidthInPx = inPx;
    },

    open: async function (animate = true) {
      if (this.isActive) {
        return;
      }

      if (this.cancelToken) {
        this.ignoreFailedRequest = true;
        this.cancelToken.cancel();
      }

      this.isActive = true;
      this.trigger('beforeOpen');

      $(document.activeElement).trigger('blur');

      if (!this.$editorContainer) {
        this._buildUi();
      }

      // Set the sizes
      this.handleWindowResize();
      this.addListener(Garnish.$win, 'resize', 'handleWindowResize');

      this.$editorContainer.css(Craft.left, -this.editorWidthInPx + 'px');
      this.$previewContainer.css(Craft.right, -this.getIframeWidth());

      this.slideIn(animate);

      if (!this.elementEditor) {
        await this._loadElementEditor();
      }

      this.trigger('open');
    },

    _buildUi: function () {
      this.editorId = `lp-editor-${Math.floor(Math.random() * 100000000)}`;

      const previewSkipLinkText = Craft.t('app', 'Skip to {title}', {
        title: Craft.t('app', 'Top of preview'),
      });

      this.$shade = $('<div/>', {class: 'modal-shade dark'}).appendTo(
        Garnish.$bod
      );
      this.$previewWrapper = $('<div/>', {
        role: 'dialog',
        'aria-modal': 'true',
        'aria-labelledby': 'lp-preview-heading',
      }).appendTo(Garnish.$bod);
      this.$modalLabel = $('<h2/>', {
        id: 'lp-preview-heading',
        class: 'visually-hidden',
        html: Craft.t('app', 'Preview'),
      }).appendTo(this.$previewWrapper);
      this.$editorContainer = $('<form/>', {
        id: this.editorId,
        class: 'lp-editor-container',
      }).appendTo(this.$previewWrapper);
      this.$previewContainer = $('<div/>', {
        class: 'lp-preview-container',
        id: 'lp-preview-container',
      }).appendTo(this.$previewWrapper);
      this.$notifier = $('<span/>', {
        class: 'visually-hidden',
        role: 'status',
      }).appendTo(this.$previewContainer);

      this.$editorHeader = $('<header/>', {
        class: 'lp-editor-header',
      }).appendTo(this.$editorContainer);
      if (!this.settings.standaloneMode) {
        const $closeBtn = $('<button/>', {
          type: 'button',
          class: 'btn lp-close-btn',
          'data-icon': 'xmark',
          title: Craft.t('app', 'Close Preview'),
          'aria-label': Craft.t('app', 'Close Preview'),
        }).appendTo(this.$editorHeader);
        this.addListener($closeBtn, 'click', 'close');
      }
      this.$editorToolbar = $('<div/>', {class: 'lp-toolbar'}).appendTo(
        this.$editorHeader
      );
      this.$tabContainer = $('<div/>', {class: 'pane-tabs'}).appendTo(
        this.$editorToolbar
      );
      this.$content = $('<div/>', {
        class: 'lp-content loading',
      })
        .appendTo(this.$editorContainer)
        .append($('<div/>', {class: 'spinner'}));

      this.$editorFooter = $('<footer/>', {class: 'flex flex-nowrap'}).appendTo(
        this.$editorContainer
      );

      this.$dragHandle = $('<div/>', {class: 'lp-draghandle'}).appendTo(
        this.$editorContainer
      );
      $('<div/>', {class: 'flex-grow'}).appendTo(this.$editorHeader);
      this.$spinner = $('<div/>', {
        class: 'spinner hidden',
        title: Craft.t('app', 'Saving'),
      }).appendTo(this.$editorHeader);
      this.$statusMessage = $('<span/>', {
        class: 'visually-hidden',
        'aria-live': 'polite',
      }).appendTo(this.$editorHeader);
      this.$previewSkipLink = $('<a/>', {
        class: 'skip-link btn hidden',
        href: '#lp-preview-container',
        html: previewSkipLinkText,
      }).appendTo(this.$editorHeader);

      this.$previewHeader = $('<header/>', {
        class: 'lp-preview-header',
      }).appendTo(this.$previewContainer);

      this._buildDeviceTypeFieldset();

      $('<div class="flex-grow"/>').appendTo(this.$previewHeader);
      const $buttonContainer = $('<div class="buttons"/>').appendTo(
        this.$previewHeader
      );

      // Orientation toggle
      this.$orientationBtn = $('<button/>', {
        type: 'button',
        class: 'btn disabled',
        'data-icon': 'rotate',
        'aria-disabled': 'true',
        text: Craft.t('app', 'Rotate'),
        'aria-label': Craft.t('app', 'Rotate'),
      }).appendTo($buttonContainer);
      this.addListener(this.$orientationBtn, 'click', 'switchOrientation');

      // Refresh button
      this.$refreshBtn = $('<button/>', {
        type: 'button',
        class: 'btn',
        text: Craft.t('app', 'Refresh'),
        'data-icon': 'refresh',
      }).appendTo($buttonContainer);
      this.addListener(this.$refreshBtn, 'click', () => {
        this.updateIframe(false, true);
      });

      // Get the last stored orientation
      this.deviceOrientation = Craft.getLocalStorage('LivePreview.orientation');

      this.$iframeContainer = $('<div/>', {
        class: 'lp-iframe-container',
      }).appendTo(this.$previewContainer);
      this.$devicePreviewContainer = $('<div/>', {
        class: 'lp-device-preview-container',
      }).appendTo(this.$iframeContainer);
      this.$deviceMask = $('<div/>', {
        class: 'lp-device-mask',
      }).appendTo(this.$iframeContainer);

      /* Prevents focus trap bug caused by iframe as last element */
      this.$bumperLink = $('<a/>', {
        class: 'skip-link btn lp-preview-container__bumper-link',
        html: previewSkipLinkText,
        href: '#lp-preview-container',
      });
      this.$bumperLink.appendTo(this.$previewContainer);

      this.dragger = new Garnish.BaseDrag(this.$dragHandle, {
        axis: Garnish.X_AXIS,
        onDragStart: this._onDragStart.bind(this),
        onDrag: this._onDrag.bind(this),
        onDragStop: this._onDragStop.bind(this),
      });
    },

    _loadElementEditor: async function () {
      await this.settings.onBeforeLoad();

      this.cancelToken = axios.CancelToken.source();
      let response;
      try {
        response = await Craft.sendActionRequest('GET', 'elements/edit', {
          params: {
            elementType: this.settings.elementType,
            elementId: this.settings.elementId,
            draftId: this.settings.draftId,
            revisionId: this.settings.revisionId,
            siteId: this.settings.siteId,
          },
          cancelToken: this.cancelToken.token,
          headers: {
            'X-Craft-Container-Id': this.editorId,
            'X-Craft-Namespace': this.namespace,
          },
        });
      } catch (e) {
        if (!this.ignoreFailedRequest) {
          Craft.cp.displayError();
          throw e;
        }
        this.ignoreFailedRequest = false;
        return;
      } finally {
        this.$content.removeClass('loading');
        this.cancelToken = null;
      }

      const {data} = response;
      this.namespace = data.namespace;

      if (this.settings.standaloneMode) {
        if (data.actionMenu) {
          const labelId = Craft.namespaceId(
            'action-menu-label',
            this.namespace
          );
          const menuId = Craft.namespaceId('action-menu', this.namespace);
          $('<label/>', {
            id: labelId,
            class: 'visually-hidden',
            text: Craft.t('app', 'Actions'),
          }).appendTo(this.$editorHeader);
          const $actionBtn = $('<button/>', {
            class: 'btn action-btn header-btn',
            type: 'button',
            title: Craft.t('app', 'Actions'),
            'aria-controls': menuId,
            'aria-describedby': labelId,
            'data-disclosure-trigger': 'true',
          }).appendTo(this.$editorHeader);
          $(data.actionMenu).appendTo(this.$editorHeader);
          $actionBtn.disclosureMenu();
        }

        if (data.editUrl) {
          $('<a/>', {
            target: '_blank',
            class: 'btn header-btn',
            title: Craft.t('app', 'Open in a new tab'),
            'aria-label': Craft.t('app', 'Open in a new tab'),
            'data-icon': 'external',
            href: data.editUrl,
          }).appendTo(this.$editorHeader);
        }
      }

      this.$content.html(data.content);

      this.$saveBtn = Craft.ui
        .createSubmitButton({
          label: Craft.t('app', 'Save'),
          spinner: true,
        })
        .appendTo(this.$editorFooter);

      this.updateTabs(data.tabs);

      if (data.formAttributes) {
        Craft.setElementAttributes(this.$editorContainer, data.formAttributes);
      }

      this.$editorContainer.data('delta-names', response.data.deltaNames);
      this.$editorContainer.data(
        'initial-delta-values',
        response.data.initialDeltaValues
      );
      this.$editorContainer.data(
        'initialSerializedValue',
        this.$editorContainer.serialize()
      );

      // Execute the response JS first so any Selectize inputs, etc.,
      // get instantiated before field toggles
      await Craft.appendHeadHtml(data.headHtml);
      await Craft.appendBodyHtml(data.bodyHtml);
      Craft.initUiElements(this.$editorContainer);

      this.elementEditor = new Craft.ElementEditor(
        this.$editorContainer,
        Object.assign(
          {
            namespace: this.namespace,
            $contentContainer: this.$content,
            $actionBtn: this.$saveBtn,
            $spinnerContainer: this.$editorHeader,
            updateTabs: (tabs) => this.updateTabs(tabs),
            getTabManager: () => this.tabManager,
            handleSubmitResponse: (response) => {
              window.location.reload();
            },
            handleSubmitError: async (error) => {
              // We can get away with just refreshing the content since there's
              // no sidebar to worry about
              this.$saveBtn.addClass('loading');
              // Wait a sec so the `finally` block has a chance to run
              await (async () => {})();
              await this.elementEditor.refreshContent({
                [this.elementEditor.namespaceInputName('prevalidate')]: 1,
              });
              this.$saveBtn.removeClass('loading');
            },
            autosaveDrafts: true,
            saveParams: {
              setEnabled: 0,
            },
          },
          this.$editorContainer.data('elementEditorSettings')
        )
      );

      this.elementEditor.on('afterSaveDraft', ({response}) => {
        this.trigger('afterSaveDraft', {response});
      });

      this.elementEditor.on('update', () => {
        this.updateIframe();
      });

      this.elementEditor.on('beforeSubmit', () => {
        this.$saveBtn.addClass('loading');
      });

      this.elementEditor.on('afterSubmit', () => {
        this.$saveBtn.removeClass('loading');
      });

      Craft.cp.elementThumbLoader.load(this.$content);
      Craft.setFocusWithin(this.$content);

      // Preview targets
      if (this.elementEditor.settings.previewTargets.length > 1) {
        const $spacer = $('<div class="flex-grow"/>').insertAfter(
          this.$deviceTypeContainer
        );
        this.$targetBtn = $('<button/>', {
          type: 'button',
          class: 'btn menubtn',
          text: this.elementEditor.settings.previewTargets[0].label,
        }).insertAfter($spacer);
        this.$targetMenu = $('<div/>', {
          class: 'menu lp-target-menu',
        }).insertAfter(this.$targetBtn);
        const $ul = $('<ul/>', {class: 'padded'}).appendTo(this.$targetMenu);
        let $li, $a;
        for (
          let i = 0;
          i < this.elementEditor.settings.previewTargets.length;
          i++
        ) {
          $li = $('<li/>').appendTo($ul);
          $a = $('<a/>', {
            data: {target: i},
            text: this.elementEditor.settings.previewTargets[i].label,
            class: i === 0 ? 'sel' : null,
          }).appendTo($li);
        }
        new Garnish.MenuBtn(this.$targetBtn, {
          onOptionSelect: (option) => {
            this.switchTarget($(option).data('target'));
          },
        });
      }

      this.updateIframe();
      Craft.ElementThumbLoader.retryAll();

      this.$previewSkipLink.removeClass('hidden');
    },

    _getDeviceTypeTranslation: function (type) {
      let translation;
      switch (type) {
        case 'phone':
          translation = Craft.t('app', 'Mobile');
          break;
        case 'tablet':
          translation = Craft.t('app', 'Tablet');
          break;
        default:
          translation = Craft.t('app', 'Desktop');
          break;
      }
      return translation;
    },

    _getDeviceOrientationTranslation: function (orientation) {
      return orientation === 'portrait'
        ? Craft.t('app', 'Portrait')
        : Craft.t('app', 'Landscape');
    },

    _buildDeviceTypeFieldset: function () {
      // Device type buttons
      this.$deviceTypeContainer = $('<section/>', {
        class: 'btngroup lp-device-type',
        'aria-label': Craft.t('app', 'Device type'),
      }).appendTo(this.$previewHeader);
      $('<button/>', {
        type: 'button',
        class: 'btn lp-device-type-btn--desktop active',
        title: Craft.t('app', 'Desktop'),
        'aria-label': Craft.t('app', 'Desktop'),
        'aria-pressed': 'true',
        data: {
          width: '',
          height: '',
          deviceType: 'desktop',
        },
      }).appendTo(this.$deviceTypeContainer);
      $('<button/>', {
        type: 'button',
        class: 'btn lp-device-type-btn--tablet',
        title: Craft.t('app', 'Tablet'),
        'aria-label': Craft.t('app', 'Tablet'),
        'aria-pressed': 'false',
        data: {
          width: 768,
          height: 1024,
          deviceType: 'tablet',
        },
      }).appendTo(this.$deviceTypeContainer);
      $('<button/>', {
        type: 'button',
        class: 'btn lp-device-type-btn--phone',
        title: Craft.t('app', 'Mobile'),
        'aria-label': Craft.t('app', 'Mobile'),
        'aria-pressed': 'false',
        data: {
          width: 375,
          height: 667,
          deviceType: 'phone',
        },
      }).appendTo(this.$deviceTypeContainer);

      // Add functionality
      this.deviceBtnGroup = new Craft.Listbox(this.$deviceTypeContainer, {
        onChange: ($selectedOption) => {
          this.switchDeviceType($selectedOption);
        },
      });
    },

    _activeTarget: function () {
      return this.elementEditor.settings.previewTargets[this.activeTarget];
    },

    /**
     * @returns {boolean}
     * @private
     */
    _autoRefresh: function () {
      const target = this._activeTarget();
      return typeof typeof target.refresh === 'undefined' || !!target.refresh;
    },

    switchTarget: function (i) {
      this.activeTarget = i;
      this.$targetBtn.text(this.elementEditor.settings.previewTargets[i].label);
      this.$targetMenu.find('a.sel').removeClass('sel');
      this.$targetMenu.find('a').eq(i).addClass('sel');
      this.updateIframe(true);
      this.trigger('switchTarget', {
        previewTarget: this.elementEditor.settings.previewTargets[i],
      });
    },

    handleWindowResize: function () {
      // Reset the width so the min width is enforced
      this.editorWidth = this.editorWidth;

      // Update the editor/iframe sizes
      this.updateWidths();
    },

    slideIn: function (animate = true) {
      if (!this.isActive || this.isVisible) {
        return;
      }

      $('html').addClass('noscroll');
      this.$shade.velocity('fadeIn');

      this.$editorContainer
        .show()
        .velocity('stop')
        .animateLeft(
          0,
          Garnish.getUserPreferredAnimationDuration(this.animationDuration),
          () => {
            this.trigger('slideIn');
            Garnish.$win.trigger('resize');
          }
        );

      this.$previewContainer
        .show()
        .velocity('stop')
        .animateRight(
          0,
          Garnish.getUserPreferredAnimationDuration(this.animationDuration)
        );

      this.isVisible = true;

      Garnish.uiLayerManager.addLayer(this.$previewWrapper);
      Garnish.hideModalBackgroundLayers();
      Craft.setFocusWithin(this.$previewWrapper);
      Craft.trapFocusWithin(this.$previewWrapper);
      Garnish.uiLayerManager.registerShortcut(
        {
          keyCode: Garnish.S_KEY,
          ctrl: true,
        },
        async (ev) => {
          await this.elementEditor.checkForm();
          this.elementEditor.handleSubmit(ev);
        }
      );
      Garnish.uiLayerManager.registerShortcut(Garnish.ESC_KEY, () => {
        this.close();
      });

      if (!animate) {
        this.$editorContainer.velocity('finish');
        this.$previewContainer.velocity('finish');
      }
    },

    close: async function () {
      if (!this.isActive || !this.isVisible) {
        return;
      }

      // Check the form one last time
      await this.elementEditor.checkForm();

      this.trigger('beforeClose');

      $('html').removeClass('noscroll');

      this.removeListener(Garnish.$win, 'resize');
      Garnish.uiLayerManager.removeLayer();
      Garnish.resetModalBackgroundLayerVisibility();

      // Delay shade fade-out when animation is present
      if (Garnish.prefersReducedMotion()) {
        this.$shade.velocity('fadeOut');
      } else {
        this.$shade.delay(200).velocity('fadeOut');
      }

      this.$editorContainer
        .velocity('stop')
        .animateLeft(
          -this.editorWidthInPx,
          Garnish.getUserPreferredAnimationDuration(this.animationDuration),
          () => {
            this.$editorContainer.hide();
            this.trigger('slideOut');
          }
        );

      this.$previewContainer
        .velocity('stop')
        .animateRight(
          -this.getIframeWidth(),
          Garnish.getUserPreferredAnimationDuration(this.animationDuration),
          () => {
            this.$iframeContainer.removeClass('lp-iframe-container--rotating');
            this.$previewContainer.hide();
          }
        );

      Craft.ElementThumbLoader.retryAll();

      this.isActive = false;
      this.isVisible = false;
      this.trigger('close');
    },

    updateTabs: function (tabs) {
      if (this.tabManager) {
        this.tabManager.destroy();
        this.tabManager = null;
        this.$tabContainer.html('');
      }

      this.hasTabs = !!tabs;

      if (this.hasTabs) {
        const $tabContainer = $(tabs);
        this.$tabContainer.replaceWith($tabContainer);
        this.$tabContainer = $tabContainer;
        this.tabManager = new Craft.Tabs(this.$tabContainer);
        this.tabManager.on('deselectTab', (ev) => {
          $(ev.$tab.attr('href')).addClass('hidden');
        });
        this.tabManager.on('selectTab', (ev) => {
          $(ev.$tab.attr('href')).removeClass('hidden');
          Garnish.$win.trigger('resize');
          this.$content.trigger('scroll');
        });
      }
    },

    getIframeWidth: function () {
      return Garnish.$win.width() - this.editorWidthInPx;
    },

    updateWidths: function () {
      this.$editorContainer.css('width', this.editorWidthInPx + 'px');
      this.$previewContainer.width(this.getIframeWidth());
      if (this._devicePreviewIsActive()) {
        this.updateDevicePreview();
      }
    },

    _useIframeResizer: function () {
      return Craft.previewIframeResizerOptions !== false;
    },

    /**
     * @param {boolean} [resetScroll=false]
     * @param {boolean} [refresh]
     */
    updateIframe: function (resetScroll, refresh) {
      if (!this.isActive) {
        return false;
      }

      // Ignore non-boolean resetScroll values
      resetScroll = resetScroll === true;

      // If the draft ID has changed or there's no iframe, we definitely need to refresh
      if (
        this.draftId !== (this.draftId = this.elementEditor.settings.draftId) ||
        !this.$iframe
      ) {
        refresh = true;
      }

      const target = this._activeTarget();
      if (typeof refresh === 'undefined') {
        refresh = resetScroll || this._autoRefresh();
      }

      this.trigger('beforeUpdateIframe', {
        previewTarget: target,
        resetScroll: resetScroll,
        refresh: refresh,
      });

      // If this is an existing preview target, make sure it wants to be refreshed automatically
      if (!refresh) {
        this.slideIn();
        return;
      }

      this.elementEditor
        .getTokenizedPreviewUrl(target.url, 'x-craft-live-preview')
        .then((url) => {
          // Maintain the current scroll position?
          let sameHost;
          if (resetScroll) {
            this.scrollTop = null;
            this.scrollLeft = null;
          } else if (this.iframeLoaded && this.$iframe) {
            if (this._useIframeResizer()) {
              this.iframeHeight = this.$iframe.height();
              this.scrollTop = this.$iframeContainer.scrollTop();
              this.scrollLeft = this.$iframeContainer.scrollLeft();
            } else {
              sameHost = Craft.isSameHost(url);
              if (sameHost && this.$iframe[0].contentWindow) {
                this.scrollTop = $(
                  this.$iframe[0].contentWindow.document
                ).scrollTop();

                this.scrollLeft = $(
                  this.$iframe[0].contentWindow.document
                ).scrollLeft();
              }
            }
          }

          this.iframeLoaded = false;

          const $iframe = $('<iframe/>', {
            class: 'lp-preview',
            frameborder: 0,
            src: url,
            title: Craft.t('app', 'Preview'),
          });

          if (this.$iframe) {
            this.$iframe.replaceWith($iframe);
          } else {
            $iframe.appendTo(this.$devicePreviewContainer);
          }

          // Keep the iframe height consistent with its content
          if (this._useIframeResizer()) {
            if (!resetScroll && this.iframeHeight !== null) {
              $iframe.height(this.iframeHeight);
              this.$iframeContainer.scrollTop(this.scrollTop);
              this.$iframeContainer.scrollLeft(this.scrollLeft);
            }

            iFrameResize(
              $.extend(
                {
                  checkOrigin: false,
                  // Allow iframe scrolling until we've successfully initialized the resizer
                  scrolling: true,
                  onInit: (iframe) => {
                    this.iframeLoaded = true;
                    this.iframeHeight = null;
                    this.scrollTop = null;
                    this.scrollLeft = null;
                    iframe.scrolling = 'no';
                  },
                },
                Craft.previewIframeResizerOptions || {}
              ),
              $iframe[0]
            );
          } else {
            $iframe.on('load', () => {
              this.iframeLoaded = true;
              if (!resetScroll && sameHost) {
                $iframe[0].contentWindow.scrollTo(
                  this.scrollLeft || 0,
                  this.scrollTop || 0
                );
              }
            });
          }

          this.url = url;
          this.$iframe = $iframe;

          if (this._devicePreviewIsActive()) {
            this.updateDevicePreview();
          }

          this.trigger('afterUpdateIframe', {
            previewTarget:
              this.elementEditor.settings.previewTargets[this.activeTarget],
            $iframe: this.$iframe,
          });

          this.slideIn();
        });
    },

    _devicePreviewIsActive: function () {
      return this.currentDeviceType !== 'desktop';
    },

    _updateNotifier: function () {
      this.$notifier.html = '';

      const translation =
        this.currentDeviceType === 'desktop'
          ? 'Previewing {type} device'
          : 'Previewing {type} device in {orientation}';
      let params = {
        type: this._getDeviceTypeTranslation(this.currentDeviceType),
      };

      if (this.currentDeviceType !== 'desktop') {
        params = {
          ...params,
          ...{
            orientation: this._getDeviceOrientationTranslation(
              this.deviceOrientation
            ),
          },
        };
      }

      const message = Craft.t('app', translation, params);

      setTimeout(() => {
        this.$notifier.text(message);
      }, 200);
    },

    switchDeviceType: function ($option) {
      this.$iframeContainer.removeClass('lp-iframe-container--rotating');

      const newDeviceType = $option.data('deviceType');
      // Bail if we’re just smashing the same button
      if (newDeviceType === this.currentDeviceType) {
        return false;
      }

      // Store new device type data
      this.currentDeviceType = newDeviceType;
      this.deviceWidth = $option.data('width');
      this.deviceHeight = $option.data('height');

      if (this.currentDeviceType === 'desktop') {
        // Disable the orientation button
        this.$orientationBtn.addClass('disabled').attr('aria-disabled', 'true');

        this.$iframeContainer.removeClass(
          'lp-iframe-container--has-device-preview'
        );
      } else {
        // Enable the orientation button
        this.$orientationBtn
          .removeClass('disabled')
          .removeAttr('aria-disabled');

        this.$iframeContainer.addClass(
          'lp-iframe-container--has-device-preview'
        );
      }

      // Add the tablet class if needed
      if (this.currentDeviceType === 'tablet') {
        this.$iframeContainer.addClass('lp-iframe-container--tablet');
      } else {
        this.$iframeContainer.removeClass('lp-iframe-container--tablet');
      }

      this._updateNotifier();

      if (this.currentDeviceType !== 'desktop') {
        this.updateDevicePreview();
      }
    },

    switchOrientation: function () {
      if (!this._devicePreviewIsActive()) {
        return false;
      }

      // Switch to whichever orientation is currently not stored
      if (!this.deviceOrientation || this.deviceOrientation === 'portrait') {
        this.deviceOrientation = 'landscape';
      } else {
        this.deviceOrientation = 'portrait';
      }

      // Store the new one
      Craft.setLocalStorage('LivePreview.orientation', this.deviceOrientation);

      // Allow the animation to take place
      this.$iframeContainer.addClass('lp-iframe-container--rotating');

      // Update the device preview
      this.updateDevicePreview();
      this._updateNotifier();

      setTimeout(() => {
        this.$iframeContainer.removeClass('lp-iframe-container--rotating');
      }, 300);
    },

    updateDevicePreview: function () {
      // Figure out the best zoom
      let hZoom = 1;
      let wZoom = 1;
      let zoom = 1;
      let previewHeight = this.$previewContainer.height() - 50 - 48; // 50px for the header bar and 24px clearance
      let previewWidth = this.$previewContainer.width() - 48;
      let maskHeight = this.deviceMaskDimensions[this.currentDeviceType].height;
      let maskWidth = this.deviceMaskDimensions[this.currentDeviceType].width;

      if (this.deviceOrientation === 'landscape') {
        if (previewWidth < maskHeight) {
          hZoom = previewWidth / maskHeight;
        }
        if (previewHeight < maskWidth) {
          wZoom = previewHeight / maskWidth;
        }
      } else {
        if (previewHeight < maskHeight) {
          hZoom = previewHeight / maskHeight;
        }
        if (previewWidth < maskWidth) {
          wZoom = previewWidth / maskWidth;
        }
      }

      zoom = hZoom;
      if (wZoom < hZoom) {
        zoom = wZoom;
      }

      // Figure out the css values
      const translate = -(100 / zoom / 2);
      const rotationDeg =
        this.deviceOrientation === 'landscape' ? '-90deg' : '0deg';

      // Apply first to the device mask
      this.$deviceMask.css({
        width: this.deviceMaskDimensions[this.currentDeviceType].width + 'px',
        height: this.deviceMaskDimensions[this.currentDeviceType].height + 'px',
        transform:
          'scale(' +
          zoom +
          ') translate(' +
          translate +
          '%, ' +
          translate +
          '%) rotate(' +
          rotationDeg +
          ')',
      });

      if (this.deviceOrientation === 'landscape') {
        this.$devicePreviewContainer.css({
          width: this.deviceHeight + 'px',
          height: this.deviceWidth + 'px',
          transform:
            'scale(' +
            zoom +
            ') translate(' +
            translate +
            '%, ' +
            translate +
            '%)',
          marginTop: 0,
          marginLeft: '-' + 12 * zoom + 'px',
        });
      } else {
        this.$devicePreviewContainer.css({
          width: this.deviceWidth + 'px',
          height: this.deviceHeight + 'px',
          transform:
            'scale(' +
            zoom +
            ') translate(' +
            translate +
            '%, ' +
            translate +
            '%)',
          marginTop: '-' + 12 * zoom + 'px',
          marginLeft: 0,
        });
      }
    },

    _onDragStart: function () {
      this.dragStartEditorWidth = this.editorWidthInPx;
      this.$previewContainer.addClass('dragging');
    },

    _onDrag: function () {
      if (Craft.orientation === 'ltr') {
        this.editorWidth = this.dragStartEditorWidth + this.dragger.mouseDistX;
      } else {
        this.editorWidth = this.dragStartEditorWidth - this.dragger.mouseDistX;
      }

      if (this.tabManager) {
        this.tabManager.updateMenuBtn();
      }

      this.updateWidths();
      this.trigger('drag');
    },

    _onDragStop: function () {
      this.$previewContainer.removeClass('dragging');
      Craft.setLocalStorage('LivePreview.editorWidth', this.editorWidth);
    },

    destroy: function () {
      Craft.Preview.instances = Craft.Preview.instances.filter(
        (o) => o !== this
      );
      this.elementEditor.destroy();
      delete this.elementEditor;
      this.base();
    },
  },
  {
    defaultEditorWidth: 0.33,
    minEditorWidthInPx: 320,
    instances: [],

    defaults: {
      elementType: null,
      elementId: null,
      draftId: null,
      revisionId: null,
      siteId: null,
      standaloneMode: false,
      onBeforeLoad: async () => {},
    },

    refresh: function () {
      for (let preview of Craft.Preview.instances) {
        preview.updateIframe();
      }
      for (let preview of Craft.LivePreview.instances) {
        preview.forceUpdateIframe();
      }
    },

    getActive: function () {
      for (let preview of Craft.Preview.instances) {
        if (preview.isActive) {
          return preview;
        }
      }
    },
  }
);
