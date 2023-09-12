(function ($) {
  var BLOCK_TYPE_SINGLE = 'single';
  var BLOCK_TYPE_MULTIPLE = 'multiple';

  var commonImageLink = {
    imageUrl: {
      label: 'Image Url',
      name: 'image-url', 
    },
    redirectUrl: {
      label: 'Redirect Url',
      name: 'redirect-url', 
    },
    title: {
      label: 'Title',
      name: 'title', 
    },
  };

  var repeaterFields = {
    banner: {
      image: {
        label: 'Image Url',
        name: 'banner-image', 
      },
      title: {
        label: 'Title',
        name: 'banner-title', 
      },
      subtitle: {
        label: 'Subtitle',
        name: 'banner-subtitle', 
      },
      CTAUrl: {
        label: 'Call to action Url',
        name: 'banner-cta-url', 
      },
      CTAText: {
        label: 'Call to action text',
        name: 'banner-cta-text', 
      },
    },
    clients: {
      image: {
        label: 'Image Url',
        name: 'company-image', 
      },
      redirectUrl: {
        label: 'Redirect Url',
        name: 'company-redirect-url', 
      },
      name: {
        label: 'Name',
        name: 'company-name', 
      },
    },
    testimonials: {
      authorName: {
        label: 'Author Name',
        name: 'testimony-author-name', 
      },
      authorPosition: {
        label: 'Author Position',
        name: 'testimony-author-position', 
      },
      text: {
        label: 'Text',
        name: 'testimony-text', 
        fieldType: 'textarea',
      },
    },
    categories: commonImageLink,
    cardGroup: commonImageLink,
    cardGroupSlider: commonImageLink,
    blogPosts: commonImageLink,
    list: {
      text: {
        label: 'Text',
        name: 'list-text', 
      },
      redirectUrl: {
        label: 'Redirect Url',
        name: 'list-redirect-url', 
      },
    }
  }

  var dynamicConfigFields = {
    text: {
      classes: {
        label: 'Classes',
        name: 'text-classes',
      },
      text: {
        label: 'Content',
        name: 'text-content',
      }
    },
    textarea: {
      classes: {
        label: 'Classes',
        name: 'text-classes',
      },
      text: {
        label: 'Content',
        name: 'text-content',
        fieldType: 'textarea'
      }
    },
    menu: {
      menuId: {
        label: 'Menu ID',
        name: 'menu-id',
      }
    },
    callToAction: {
      classes: {
        label: 'Classes',
        name: 'text-classes',
      },
      text: {
        label: 'Text',
        name: 'cta-text',
      },
      redirectUrl: {
        label: 'Redirect Url',
        name: 'cta-redirect-url',
      }
    },
    singleImage: {
      classes: {
        label: 'Classes',
        name: 'image-classes',
      },
      imageUrl: {
        label: 'Image Url',
        name: 'image-url',
      },
      redirectUrl: {
        label: 'Redirect Url',
        name: 'redirect-url',
      },
      redirectType: {
        label: 'Redirect type',
        name: 'redirect-type',
      },
    },
    products: {
      productId: {
        label: 'Product Id',
        name: 'list-text', 
      }
    },
  }

  var REPEATER_FIELD_KEYS = Object.keys(repeaterFields);

  var countries = [];
  if ($( 'input#available-countries').length > 0) {
    countries = JSON.parse($( 'input#available-countries').val() || [])
  }
  var baseCountry = $( 'input#base-country').val() || '';

  var blazeCommerce = {
    syncResultsContainer: '#sync-results-container',
    syncProductLink: '#sync-product-link',
    syncTaxonomiesLink: '#sync-taxonomies-link',
    syncMenusLink: '#sync-menus-link',
    syncPagesLink: '#sync-pages-link',
    syncSiteInfoLink: '#sync-site-info-link',
    syncAllLink: '#sync-all-link',

    syncInProgress: false,

    renderLoader: function(message) {
      if ($('#blaze-commerce-loader').length === 0) {
        $(this.syncResultsContainer).append('<img id="blaze-commerce-loader" src="/wp-includes/js/thickbox/loadingAnimation.gif" />')
      }

      if ($('#blaze-commerce-loader-message').length === 1) {
        $('#blaze-commerce-loader-message').remove();
      }
        
      $(this.syncResultsContainer).append('<div id="blaze-commerce-loader-message">' + message + '</div>')
    },

    hideLoader: function() {
      $('#blaze-commerce-loader').remove();
      $('#blaze-commerce-loader-message').remove();
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

      $(document.body).find( '.blaze-commerce-multiple-select' ).chosen();

      // if ( jQuery().chosen ) {
      //   $(document.body).find( '.blaze-commerce-multiple-select' ).chosen();
      // }

      this.initializeDragabbleContents();
    },

    disableDroppedElement: function(element) {
      var droppedElement = $(element);
      var blockId = droppedElement.data('block_id');
      var blockType = droppedElement.data('block_type');
      var blockElement = $('.blaze-commerce-draggable-panel').find('.blaze-commerce-draggable-block[data-block_id="' + blockId + '"]');

      if (blockType === BLOCK_TYPE_SINGLE) {
        blockElement.draggable('disable');
        blockElement.addClass('disabled');
      }
    },

    addRemoveButtonToDroppedElement: function(element) {
      var droppedElement = $(element);
      var blockId = droppedElement.data('block_id');
      var blockElement = $('.blaze-commerce-draggable-panel').find('.blaze-commerce-draggable-block[data-block_id="' + blockId + '"]');

      if (droppedElement.find('.remove').length === 0) {
        var removeButton = $( '<span class="remove">✕</span>' );
        removeButton.on('click', function() {
          droppedElement.remove();
          blockElement.draggable('enable');
          blockElement.removeClass('disabled');
          blazeCommerce.generateSaveData();
        });
        droppedElement.find('.content').append(removeButton)
      }
    },
    

    addCollapsedConfig: function(element) {
      var blockElement = $(element);
      var blockId = blockElement.data('block_id');
      // var blockElement = $('.blaze-commerce-draggable-canvas').find('.blaze-commerce-draggable-block[data-block_id="' + blockId + '"]');
      
      if (blockElement.find('.configuration').length > 0) {
        return;
      }
      var caretState = $('<span class="caret-status dashicons"></span>')
      if (  blockElement.find('.content .caret-status').length == 0 ) {
        blockElement.find('.content').append(caretState)
      }

      var configContent = configurationTemplate(blockId);

      blockElement.append(configContent);

      loadConfigData(blockElement, blockId);
    },

    initializeDragabbleContents: function() {
      $('.blaze-commerce-draggable-block').draggable({
        connectToSortable: ".blaze-commerce-draggable-canvas",
        opacity: 0.7,
        helper: "clone",
        stop: function (event, ui) {
          console.log('stop', event);
        }
      });
      $('.blaze-commerce-draggable-canvas').droppable({
        accept: ".blaze-commerce-draggable-block",
        drop: function( event, ui ) {
          blazeCommerce.disableDroppedElement(ui.helper);
          console.log(ui);
          blazeCommerce.addCollapsedConfig(ui.helper);
        },
      });

      $('.blaze-commerce-draggable-canvas').sortable({
        stop: function(e, ui) {
          console.log('sortable stop');
          // blazeCommerce.generateSaveData();
          generateMetaDataFromElement(ui.item);
          $('.blaze-commerce-draggable-canvas').sortable( "refresh" );
        }
      });

      this.loadInitialData();
      $('.blaze-commerce-draggable-canvas').sortable( "refresh" );
    },

    generateSaveData: function() {
      const data = $.map($('.blaze-commerce-draggable-canvas').find('.blaze-commerce-draggable-block'), function(el) {
        var $el = $(el);
        var blockType = $el.data('block_type');
        var blockId = $el.data('block_id');
        var metaData = $el.data('block_metadata');
        var index = $el.index();
        console.log(blockId, metaData, $el);
        return {
          position: index,
          blockType: blockType,
          blockId: blockId,
          metaData: metaData,
        };
      })

      console.log(data);

      $('input#draggable_result').val(JSON.stringify(data));
    },
    loadInitialData: function() {
      var datas = [];
      if ($('input#draggable_result').length > 0) {
        datas = JSON.parse($('input#draggable_result').val());
      }
      if (!Array.isArray(datas)) {
        datas = [];
      }
      $('.blaze-commerce-draggable-canvas').sortable( "refresh" );
      datas.forEach(function(element) {
        var blockElement = $('.blaze-commerce-draggable-block[data-block_id="' + element.blockId + '"]').first().clone();
        blockElement.data('block_metadata', element.metaData)
        if (element.blockType === BLOCK_TYPE_SINGLE) {
          blazeCommerce.disableDroppedElement(blockElement);
        }

        blazeCommerce.addCollapsedConfig(blockElement);

        blockElement.appendTo('.blaze-commerce-draggable-canvas');
      });
      console.log(datas);
    }
  }

  function configurationTemplate(blockId) {
    if (REPEATER_FIELD_KEYS.includes(blockId)) {
      var configContent = $('<div class="configuration">' + repeaterTemplate() + '</div>');
      configContent.find('.items').sortable({
        stop: function(e, ui) {
          generateMetaDataFromElement(ui.item)
        }
      });
      return configContent;
    } else {
      var configContent = $('<div class="configuration">' + footerTemplate() + '</div>');
      return configContent;
    }
  }

  function repeaterTemplate() {
    var template = ['<div class="items"></div>'];
    template.push(footerTemplate({ hasAddItemButton: true }));
    return template.join('');
  }

  function footerTemplate(config = {}) {
    var template = [];
    template.push('<div class="footer">');
    if (config.hasAddItemButton) {
      template.push('<button class="button button-primary add-item">Add Item</button>');
    }
    template.push('<button class="button button-danger delete-block">Delete</button>');
    template.push('</div>');

    return template.join('');
  }

  function rowItemTemplate(blockId, data = false) {
    var generatedFields = [];
    var defaultData = {};
    var initialFieldValues = {};
    for (var key in repeaterFields[blockId]) {
      var label = repeaterFields[blockId][key].label;
      var name = repeaterFields[blockId][key].name;
      var fieldType = repeaterFields[blockId][key].fieldType;
      generatedFields.push('<div class="input-wrapper"><label>'+label+'</label>: ' + getFormField(name, fieldType) + '</div>');

      initialFieldValues[key] = '';
    }
    if (!data) {
      data = initialFieldValues;
    }
    var itemEl = $('<div class="row-item"><span class="remove">✕</span>'+generatedFields.join('')+'</div>')

    countries.forEach(function(country) {
      defaultData[country] = data;
    });

    itemEl.data('row-data', defaultData)
    return itemEl;
  }

  function addRowItem(element, blockId) {
    if (typeof repeaterFields[blockId] === 'undefined') return '';

    var itemEl = rowItemTemplate(blockId);
    element.find('.configuration .items').append(itemEl);
  }

  function dynamicConfigRowTemplate(blockId) {
    var generatedFields = [];
    var initialFieldValues = {};
    for (var key in dynamicConfigFields[blockId]) {
      var label = dynamicConfigFields[blockId][key].label;
      var name = dynamicConfigFields[blockId][key].name;
      var fieldType = dynamicConfigFields[blockId][key].fieldType;
      generatedFields.push('<div class="input-wrapper"><label>'+label+'</label>: ' + getFormField(name, fieldType) + '</div>');

      initialFieldValues[key] = '';
    }
    var itemEl = $('<div class="row-item">' + generatedFields.join('') + '</div>');
    return itemEl;
  }

  function getFormField(name, fieldType) {
    var field;
    switch (fieldType) {
      case 'textarea':
        field = '<textarea class="input '+name+'"></textarea>'
        break;
      default:
        field = '<input type="text" class="input '+name+'" />'
        break;
    }

    return field;
  }

  function addConfigFields(element, blockId, metaData = false) {
    if (typeof dynamicConfigFields[blockId] === 'undefined') return '';

    var itemEl = dynamicConfigRowTemplate(blockId);

    element.data('block_metadata', metaData)
    
    element.find('.configuration').prepend(itemEl);
  }

  function loadConfigData(element, blockId) {
    var metaData = element.data('block_metadata');
    var blockType = element.data('block_type');
    var selectedCountry = $('select#region_selector').val();

    if (REPEATER_FIELD_KEYS.includes(blockId)) {
      if (metaData && metaData.length > 0) {
        metaData.forEach(function(data) {
          var itemEl = rowItemTemplate(blockId);
          itemEl.data('row-data', data);
  
          for (var key in repeaterFields[blockId]) {
            var name = repeaterFields[blockId][key].name;
            itemEl.find('.input.' + name).val(data[selectedCountry][key]);
          }
  
          element.find('.configuration .items').append(itemEl);
        })
      } else {
        addRowItem(element, blockId);
      }
    } else {
      if (typeof metaData !== 'undefined') {
        var itemEl = dynamicConfigRowTemplate(blockId);

        for (var key in dynamicConfigFields[blockId]) {
          var name = dynamicConfigFields[blockId][key].name;
          itemEl.find('.input.' + name).val(metaData[selectedCountry] ? metaData[selectedCountry][key] : '');
        }

        itemEl.insertBefore(element.find('.configuration .footer'));
      } else {
        addConfigFields(element, blockId, metaData);
      }
    }
  }

  function generateMetaDataFromElement(element) {
    var el = $(element)
    var elementBlock = el.closest('.blaze-commerce-draggable-block');
    var blockType = elementBlock.data('block_type');
    var blockId = elementBlock.data('block_id');
    var data = {};

    if (REPEATER_FIELD_KEYS.includes(blockId)) {
      var items = elementBlock.find('.items');
      data = generateRowItemsData(elementBlock, items);
    } else {
      data = generateDynamicConfigData(elementBlock);
    }

    console.log(elementBlock);
    console.log(data);
    elementBlock.data('block_metadata', data);

    console.log('generateMetaDataFromElement');
    
    blazeCommerce.generateSaveData();
  }

  function generateRowItemsData(element, itemsElement) {
    var selectedCountry = $('select#region_selector').val();
    var blockId = element.data('block_id')

    const data = $.map(itemsElement.find('.row-item'), function(item) {
      var itemEl = $(item);
      var rowData = itemEl.data('row-data');
      var _data = {};
      for (var key in repeaterFields[blockId]) {
        var name = repeaterFields[blockId][key].name;
        _data[key] = itemEl.find('.input.' + name).val();
      }

      rowData[selectedCountry] = _data;

      itemEl.data('row-data', rowData)

      return rowData;
    });

    return data;
  }

  function generateDynamicConfigData(element) {
    var selectedCountry = $('select#region_selector').val();
    var blockId = element.data('block_id')
    var blockMetadata = element.data('block_metadata')

    var _data = {};
    var initialFieldValue = {};
    for (var key in dynamicConfigFields[blockId]) {
      var name = dynamicConfigFields[blockId][key].name;
      console.log('.input.' + name);
      _data[key] = element.find('.input.' + name).val();
      initialFieldValue[key] = '';
    }

    if (!blockMetadata) {
      blockMetadata = {};
      countries.forEach(function(country) {
        blockMetadata[country] = initialFieldValue;
      });
    }
    
    blockMetadata[selectedCountry] = _data;
    console.log('generateDynamicConfigData', blockMetadata);
    
    return blockMetadata;
  }

  $(document).ready(function() {
    blazeCommerce.init();

    $(document.body).on('click', '.blaze-commerce-draggable-canvas .blaze-commerce-draggable-block', function() {
      $(this).toggleClass('open');

    });
    $(document.body).on('click', '.blaze-commerce-draggable-canvas .blaze-commerce-draggable-block .configuration', function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();
    });

    $(document.body).on('click', '.blaze-commerce-draggable-block .configuration .add-item', function(e) {
      var element = $(this).closest('.blaze-commerce-draggable-block')
      var blockId = element.data('block_id');
      addRowItem(element, blockId);
    });

    $(document.body).on('click', '.blaze-commerce-draggable-block .configuration .row-item .remove', function(e) {
      var items = $(this).closest('.items');
      $(this).closest('.row-item').remove();
      generateMetaDataFromElement(items);
    });

    $(document.body).on('click', '.blaze-commerce-draggable-block .configuration .delete-block', function(e) {
      var droppedElement = $(this).closest('.blaze-commerce-draggable-block');
      var blockId = droppedElement.data('block_id');
      var blockElement = $('.blaze-commerce-draggable-panel').find('.blaze-commerce-draggable-block[data-block_id="' + blockId + '"]');

      droppedElement.remove();
      blockElement.draggable('enable');
      blockElement.removeClass('disabled');
      blazeCommerce.generateSaveData();
    });

    $(document.body).on('blur', '.blaze-commerce-draggable-block .row-item .input', function() {
      generateMetaDataFromElement(this)
    });

    $(document.body).on('change', 'select#region_selector', function(e) {
      var selectedRegion = e.target.value;
      console.log('selectedRegion', selectedRegion);
      $.each($('.blaze-commerce-draggable-canvas .blaze-commerce-draggable-block'), function(index, block) {
        console.log(block, 'block');
        var blockId = $(block).data('block_id');
        var blockType = $(block).data('block_type');

        if (REPEATER_FIELD_KEYS.includes(blockId)) {
          var items = $(block).find('.configuration .items .row-item');
          if (items.length === 0) {
            return;
          }
          console.log(items, 'items');
          $.each(items, function(i, item) {
            var itemEl = $(item)
            var rowData = itemEl.data('row-data');
            if (rowData && rowData[selectedRegion]) {
              for (var key in repeaterFields[blockId]) {
                var name = repeaterFields[blockId][key].name;
                itemEl.find('.input.' + name).val(rowData[selectedRegion][key]);
              }
            }
          });
        } else {
          var itemEl = $(block).find('.configuration .row-item');
          var metaData = $(block).data('block_metadata');
          if (metaData && metaData[selectedRegion]) {
            for (var key in dynamicConfigFields[blockId]) {
              var name = dynamicConfigFields[blockId][key].name;
              itemEl.find('.input.' + name).val(metaData[selectedRegion][key]);
            }
          }
        }
        
      });
    });
  });
})(jQuery);
