(function ($) {
  var blazeWooless = {
    syncResultsContainer: '#sync-results-container',
    syncProductLink: '#sync-product-link',
    syncTaxonomiesLink: '#sync-taxonomies-link',
    syncMenusLink: '#sync-menus-link',
    syncPagesLink: '#sync-pages-link',
    syncSiteInfoLink: '#sync-site-info-link',
    syncAllLink: '#sync-all-link',

    syncInProgress: false,

    renderLoader: function(message) {
      if ($('#wooless-loader').length === 0) {
        $(this.syncResultsContainer).append('<img id="wooless-loader" src="/wp-includes/js/thickbox/loadingAnimation.gif" />')
      }

      if ($('#wooless-loader-message').length === 1) {
        $('#wooless-loader-message').remove();
      }
        
      $(this.syncResultsContainer).append('<div id="wooless-loader-message">' + message + '</div>')
    },

    hideLoader: function() {
      $('#wooless-loader').remove();
      $('#wooless-loader-message').remove();
    },

    clearResultContainer: function() {
      $(this.syncResultsContainer).html('');
    },

    registerEvents: function() {
      $(document.body).on('click', this.syncProductLink, this.importProducts.bind(this));
      $(document.body).on('click', this.syncTaxonomiesLink, this.importTaxonomies.bind(this));
      $(document.body).on('click', this.syncMenusLink, this.importMenus.bind(this));
      $(document.body).on('click', this.syncPagesLink, this.importPages.bind(this));
      $(document.body).on('click', this.syncSiteInfoLink, this.importSiteInfo.bind(this));
      $(document.body).on('click', this.syncAllLink, this.importAll.bind(this));
    },

    importData: function (collection, message, hideLoader = false) {
      var _this = this;
      return new Promise(function(resolve, reject) {
        var data = {
          'action': 'index_data_to_typesense',
          'collection_name': collection,
        };
  
        _this.renderLoader( message );
        _this.syncInProgress = true;
  
        $.post(ajaxurl, data, function(response) {
          $(_this.syncResultsContainer).append('<div>' + response + '</div>');
          if (hideLoader) {
            _this.hideLoader();
            _this.syncInProgress = false;
          }
          resolve(true);
        });
      })
    },

    importProducts: function(e) {
      e.preventDefault();
      if (this.syncInProgress) {
        return false;
      }
      this.clearResultContainer();
      return this.importData( 'products', 'Product Syncing in progress...', true );
    },

    importTaxonomies: function(e) {
      e.preventDefault();
      if (this.syncInProgress) {
        return false;
      }
      this.clearResultContainer();
      return this.importData( 'taxonomy', 'Taxonomies Syncing in progress...', true );
    },

    importMenus: function(e) {
      e.preventDefault();
      if (this.syncInProgress) {
        return false;
      }
      this.clearResultContainer();
      return this.importData( 'menu', 'Menus Syncing in progress...', true );
    },

    importPages: function(e) {
      e.preventDefault();
      if (this.syncInProgress) {
        return false;
      }
      this.clearResultContainer();
      return this.importData( 'page', 'Pages Syncing in progress...', true );
    },

    importSiteInfo: function(e) {
      e.preventDefault();
      if (this.syncInProgress) {
        return false;
      }
      this.clearResultContainer();
      return this.importData( 'site_info', 'Site Info Syncing in progress...', true );
    },

    importAll: function(e) {
      var _this = this;
      if (this.syncInProgress) {
        return false;
      }
      (async function() {
        _this.clearResultContainer();
        await _this.importData( 'products', 'Product Syncing in progress...' );
        await _this.importData( 'taxonomy', 'Taxonomies Syncing in progress...' );
        await _this.importData( 'menu', 'Menus Syncing in progress...' );
        await _this.importData( 'page', 'Pages Syncing in progress...' );
        await _this.importData( 'site_info', 'Site Info Syncing in progress...' );
        _this.hideLoader();
        _this.syncInProgress = false;
      })();
    },

    init: function() {
      this.registerEvents();

      $(document.body).find( '.wooless-multiple-select' ).chosen();

      // if ( jQuery().chosen ) {
      //   $(document.body).find( '.wooless-multiple-select' ).chosen();
      // }

      this.initializeDragabbleContents();
    },

    disableDroppedElement: function(element) {
      var droppedElement = $(element);
      var blockId = droppedElement.data('block_id');
      var blockType = droppedElement.data('block_type');
      var blockElement = $('.blaze-wooless-draggable-panel').find('.blaze-wooless-draggable-block[data-block_id="' + blockId + '"]');

      if (blockType === 'static') {
        blockElement.draggable('disable');
        blockElement.addClass('disabled');
      }
    },

    addRemoveButtonToDroppedElement: function(element) {
      var droppedElement = $(element);
      var blockId = droppedElement.data('block_id');
      var blockElement = $('.blaze-wooless-draggable-panel').find('.blaze-wooless-draggable-block[data-block_id="' + blockId + '"]');

      if (droppedElement.find('.remove').length === 0) {
        var removeButton = $( '<span class="remove">✕</span>' );
        removeButton.on('click', function() {
          droppedElement.remove();
          blockElement.draggable('enable');
          blockElement.removeClass('disabled');
          blazeWooless.generateSaveData();
        });
        droppedElement.append(removeButton)
      }
    },

    initializeDragabbleContents: function() {
      $('.blaze-wooless-draggable-block').draggable({
        connectToSortable: ".blaze-wooless-draggabbe-canvas",
        opacity: 0.7,
        helper: "clone",
      });
      $('.blaze-wooless-draggabbe-canvas').droppable({
        accept: ".blaze-wooless-draggable-block",
        drop: function( event, ui ) {
          blazeWooless.disableDroppedElement(ui.helper);
          blazeWooless.addRemoveButtonToDroppedElement(ui.helper);
        },
      });
      $('.blaze-wooless-draggabbe-canvas').sortable({
        stop: function(e, ui) {
          blazeWooless.generateSaveData();
        }
      });

      this.loadInitialData();
    },

    generateSaveData: function() {
      const data = $.map($('.blaze-wooless-draggabbe-canvas').find('.blaze-wooless-draggable-block'), function(el) {
        var $el = $(el);
        var blockType = $el.data('block_type');
        var blockId = $el.data('block_id');
        var index = $el.index();
        return {
          position: index,
          blockType: blockType,
          blockId: blockId,
        };
      })

      $('input[name="homepage_layout"]').val(JSON.stringify(data));
    },
    loadInitialData: function() {
      var datas = JSON.parse($('input[name="homepage_layout"]').val());
      datas.forEach(function(element) {
        $('.blaze-wooless-draggable-block[data-block_id="' + element.blockId + '"]').clone().appendTo('.blaze-wooless-draggabbe-canvas');
      });
      $('.blaze-wooless-draggabbe-canvas').sortable( "refresh" );
      datas.forEach(function(element) {
        if (element.blockType === 'static') {
          var blockElement = $('.blaze-wooless-draggabbe-canvas').find('.blaze-wooless-draggable-block[data-block_id="' + element.blockId + '"]')
          blazeWooless.disableDroppedElement(blockElement);
          blazeWooless.addRemoveButtonToDroppedElement(blockElement);
        }
      });
      console.log(datas);
    }
  }

  $(document).ready(function() {
    blazeWooless.init();
  });
})(jQuery);
