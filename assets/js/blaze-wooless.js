(function ($) {
  var blockConfig = {
    banner: {
      blockId: 'banner',
      blockType: 'single',
      configurations: [
        {
          type: 'repeater',
          fields: [
            { name: 'banner-image', type: 'text' },
            { name: 'banner-title', type: 'text' },
            { name: 'banner-subtitle', type: 'text' },
            { name: 'banner-cta-url', type: 'text' },
            { name: 'banner-cta-text', type: 'text' },
          ]
        }
      ]
    }
  }

  var countries = [];
  if ($( 'input#available-countries').length > 0) {
    countries = JSON.parse($( 'input#available-countries').val() || [])
  }
  var baseCountry = $( 'input#base-country').val() || '';

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
        droppedElement.find('.content').append(removeButton)
      }
    },
    

    addCollapsedConfig: function(element) {
      var droppedElement = $(element);
      var blockId = droppedElement.data('block_id');
      var blockMetaData = droppedElement.data('block_metadata');
      console.log('blockMetaData', blockMetaData);
      var blockElement = $('.blaze-wooless-draggable-canvas').find('.blaze-wooless-draggable-block[data-block_id="' + blockId + '"]');
      
      console.log(blockMetaData);
      if (blockElement.find('.configuration').length > 0) {
        return;
      }
      var caretState = $('<span class="caret-status dashicons"></span>')
      blockElement.find('.content').append(caretState)

      var configContent = $('<div class="configuration">' + configurationTemplate(blockId) + '</div>');

      if (blockId === 'banner') {
        configContent.find('.items').sortable({
          stop: function(e, ui) {
            generateMetaDataFromElement(ui.item)
          }
        });
      }
      blockElement.append(configContent);

      loadConfigData(blockId);
    },

    initializeDragabbleContents: function() {
      $('.blaze-wooless-draggable-block').draggable({
        connectToSortable: ".blaze-wooless-draggable-canvas",
        opacity: 0.7,
        helper: "clone",
      });
      $('.blaze-wooless-draggable-canvas').droppable({
        accept: ".blaze-wooless-draggable-block",
        drop: function( event, ui ) {
          blazeWooless.disableDroppedElement(ui.helper);
          // blazeWooless.addRemoveButtonToDroppedElement(ui.helper);
          blazeWooless.addCollapsedConfig(ui.helper);
        },
      });
      $('.blaze-wooless-draggable-canvas').sortable({
        stop: function(e, ui) {
          blazeWooless.generateSaveData();
        }
      });

      this.loadInitialData();

      $('.blaze-wooless-draggable-canvas').sortable({
        stop: function(e, ui) {
          blazeWooless.generateSaveData();
        }
      });
    },

    generateSaveData: function() {
      const data = $.map($('.blaze-wooless-draggable-canvas').find('.blaze-wooless-draggable-block'), function(el) {
        var $el = $(el);
        var blockType = $el.data('block_type');
        var blockId = $el.data('block_id');
        var metaData = $el.data('block_metadata');
        var index = $el.index();
        return {
          position: index,
          blockType: blockType,
          blockId: blockId,
          metaData: metaData,
        };
      })

      $('input[name="homepage_layout"]').val(JSON.stringify(data));
    },
    loadInitialData: function() {
      var datas = [];
      if ($('input[name="homepage_layout"]').length > 0) {
        datas = JSON.parse($('input[name="homepage_layout"]').val());
      }
      datas.forEach(function(element) {
        $('.blaze-wooless-draggable-block[data-block_id="' + element.blockId + '"]').clone().appendTo('.blaze-wooless-draggable-canvas');
      });
      $('.blaze-wooless-draggable-canvas').sortable( "refresh" );
      datas.forEach(function(element) {
        if (element.blockType === 'static') {
          var blockElement = $('.blaze-wooless-draggable-canvas').find('.blaze-wooless-draggable-block[data-block_id="' + element.blockId + '"]')
          blockElement.data('block_metadata', element.metaData)
          blazeWooless.disableDroppedElement(blockElement);
          // blazeWooless.addRemoveButtonToDroppedElement(blockElement);
          blazeWooless.addCollapsedConfig(blockElement);
        }
      });
      console.log(datas);
    }
  }

  function configurationTemplate(blockId) {
    switch (blockId) {
      case "banner":
        return repeaterTemplate();
      default:
        return '';
    }
  }

  function loadConfigData(blockId) {
    switch (blockId) {
      case "banner":
        return loadBannerConfigData();
      default:
        return '';
    }
  }

  function repeaterTemplate() {
    return `
      <div class="items">
      </div>
      <div class="footer">
        <button class="button button-primary add-item">Add Item</button>
        <button class="button button-danger delete-block">Delete</button>
      </div>
    `;
  }

  function bannerRowItemTemplate() {
    return `
    <div class="row-item">
      <span class="remove">✕</span>
      <div class="input-wrapper"><label>Image</label>: <input type="text" class="banner-image" /></div>
      <div class="input-wrapper"><label>Title</label>: <input type="text" class="banner-title" /></div>
      <div class="input-wrapper"><label>Subtite</label>: <input type="text" class="banner-subtitle" /></div>
      <div class="input-wrapper"><label>Call to action URL</label>: <input type="text" class="banner-cta-url" /></div>
      <div class="input-wrapper"><label>Call to action text</label>: <input type="text" class="banner-cta-text" /></div>
    </div>
    `;
  }

  function addBannerRowItem() {
    var element = $('.blaze-wooless-draggable-canvas').find('.blaze-wooless-draggable-block[data-block_id="banner"]');
    var itemEl = $(bannerRowItemTemplate());
    var defaultData = {};
    countries.forEach(function(country) {
      defaultData[country] = {
        bannerImage: '',
        bannerTitle: '',
        bannerSubtitle: '',
        bannerCTAUrl: '',
        bannerCTAText: '',
      };
    });
    itemEl.data('row-data', defaultData)
    element.find('.configuration .items').append(itemEl);
  }

  function loadBannerConfigData() {
    var element = $('.blaze-wooless-draggable-canvas').find('.blaze-wooless-draggable-block[data-block_id="banner"]');
    var metaData = element.data('block_metadata');
    console.log(loadBannerConfigData, metaData);

    if (metaData && metaData.length > 0) {
      metaData.forEach(function(data) {
        var itemEl = $(bannerRowItemTemplate());
        itemEl.find('input.banner-image').val(data.bannerImage);
        itemEl.find('input.banner-title').val(data.bannerTitle);
        itemEl.find('input.banner-subtitle').val(data.bannerSubtitle);
        itemEl.find('input.banner-cta-url').val(data.bannerCTAUrl);
        itemEl.find('input.banner-cta-text').val(data.bannerCTAText);

        element.find('.configuration .items').append(itemEl)
      })
    } else {
      addBannerRowItem();
    }
  }

  function generateMetaDataFromElement(element) {
    var el = $(element)
    var elementBlock = el.closest('.blaze-wooless-draggable-block');
    var items = elementBlock.find('.items');

    const data = generateRowItemsData(items);

    console.log(data, 'data');

    elementBlock.data('block_metadata', data);
    
    blazeWooless.generateSaveData();
  }

  function generateByCountry() {
    
  }

  function generateRowItemsData(itemsElement) {
    var selectedCountry = $('select#region_selector').val();

    const data = $.map(itemsElement.find('.row-item'), function(item) {
      var itemEl = $(item);
      var rowData = itemEl.data('row-data');
      console.log(rowData, 'rowData');
      var bannerImage = itemEl.find('input.banner-image').val();
      var bannerTitle = itemEl.find('input.banner-title').val();
      var bannerSubtitle = itemEl.find('input.banner-subtitle').val();
      var bannerCTAUrl = itemEl.find('input.banner-cta-url').val();
      var bannerCTAText = itemEl.find('input.banner-cta-text').val();

      var bannerData = {
        bannerImage: bannerImage,
        bannerTitle: bannerTitle,
        bannerSubtitle: bannerSubtitle,
        bannerCTAUrl: bannerCTAUrl,
        bannerCTAText: bannerCTAText,
      };

      rowData[selectedCountry] = bannerData;

      // var results = countries.reduce(function(carry, country) {
      //   carry[country] = bannerData;
      //   return carry;
      // }, {});

      itemEl.data('row-data', rowData)

      return rowData;
    });

    // var results = countries.reduce(function(carry, country) {
    //   carry[country] = data;
    //   return carry;
    // }, {});

    return data;
  }

  $(document).ready(function() {
    blazeWooless.init();

    $(document.body).on('click', '.blaze-wooless-draggable-canvas .blaze-wooless-draggable-block', function() {
      $(this).toggleClass('open');

    });
    $(document.body).on('click', '.blaze-wooless-draggable-canvas .blaze-wooless-draggable-block .configuration', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();
    });

    $(document.body).on('click', '.blaze-wooless-draggable-block .configuration .add-item', function(e) {
      addBannerRowItem();
    });

    $(document.body).on('click', '.blaze-wooless-draggable-block .configuration .row-item .remove', function(e) {
      var items = $(this).closest('.items');
      $(this).closest('.row-item').remove();
      generateMetaDataFromElement(items);
    });

    $(document.body).on('click', '.blaze-wooless-draggable-block .configuration .delete-block', function(e) {
      var droppedElement = $(this).closest('.blaze-wooless-draggable-block');
      var blockId = droppedElement.data('block_id');
      var blockElement = $('.blaze-wooless-draggable-panel').find('.blaze-wooless-draggable-block[data-block_id="' + blockId + '"]');

      droppedElement.remove();
      blockElement.draggable('enable');
      blockElement.removeClass('disabled');
      blazeWooless.generateSaveData();
    });

    $(document.body).on('blur', '.blaze-wooless-draggable-block .row-item input', function() {
      generateMetaDataFromElement(this)
    });

    $(document.body).on('change', 'select#region_selector', function(e) {
      var selectedRegion = e.target.value;
      $.each($('.blaze-wooless-draggable-canvas .blaze-wooless-draggable-block'), function(index, block) {
        console.log(block, 'block');
        var items = $(block).find('.configuration .items .row-item');
        if (items.length === 0) {
          return;
        }
        console.log(items, 'items');
        $.each(items, function(i, item) {
          var itemEl = $(item)
          var rowData = itemEl.data('row-data');
          itemEl.find('input.banner-image').val(rowData[selectedRegion].bannerImage);
          itemEl.find('input.banner-title').val(rowData[selectedRegion].bannerTitle);
          itemEl.find('input.banner-subtitle').val(rowData[selectedRegion].bannerSubtitle);
          itemEl.find('input.banner-cta-url').val(rowData[selectedRegion].bannerCTAUrl);
          itemEl.find('input.banner-cta-text').val(rowData[selectedRegion].bannerCTAText);
        })
      })
      
      // console.log(this, e.target.value);
      // var bannerImage = itemEl.find('input.banner-image').val();
      // var bannerTitle = itemEl.find('input.banner-title').val();
      // var bannerSubtitle = itemEl.find('input.banner-subtitle').val();
      // var bannerCTAUrl = itemEl.find('input.banner-cta-url').val();
      // var bannerCTAText = itemEl.find('input.banner-cta-text').val();
    });
  });
})(jQuery);
