/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/element-color-selector.js":
/*!**************************************************!*\
  !*** ./src/components/element-color-selector.js ***!
  \**************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "ElementColorSelector": function() { return /* binding */ ElementColorSelector; }
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

const {
  useState
} = wp.element;
const {
  ColorPicker,
  Popover
} = wp.components;
const ElementColorSelector = _ref => {
  let {
    value,
    setValue
  } = _ref;
  const [isVisible, setIsVisible] = useState(false);

  const toggleVisible = () => {
    setIsVisible(state => !state);
  };

  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    onClick: toggleVisible,
    style: {
      width: '20px',
      height: '20px',
      borderRadius: '9999px',
      backgroundColor: value,
      cursor: 'pointer',
      border: '1px solid #e0e0e0'
    }
  }, isVisible && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Popover, {
    placement: "bottom-end",
    position: "top left"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(ColorPicker, {
    color: value,
    onChange: setValue,
    enableAlpha: true,
    defaultValue: "#000"
  })));
};

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["element"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
!function() {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/element-color-selector */ "./src/components/element-color-selector.js");


const {
  createHigherOrderComponent
} = wp.compose;
const {
  Fragment,
  useState
} = wp.element;
const {
  InspectorControls
} = wp.editor;
const {
  PanelBody,
  SelectControl,
  ColorPalette,
  ColorPicker,
  __experimentalToolsPanel,
  ToolsPanelItem,
  BoxControl,
  Button,
  Popover,
  __experimentalDivider: Divider,
  Flex,
  FlexBlock,
  FlexItem
} = wp.components;
const {
  addFilter
} = wp.hooks;
const {
  __
} = wp.i18n;
const ToolsPanel = __experimentalToolsPanel; // Enable spacing control on the following blocks

const enableSpacingControlOnBlocks = ['maxmegamenu/location']; // Available spacing control options

const spacingControlOptions = [{
  label: __('None'),
  value: ''
}, {
  label: __('Small'),
  value: 'small'
}, {
  label: __('Medium'),
  value: 'medium'
}, {
  label: __('Large'),
  value: 'large'
}];
const maxMegaMenuAttributes = {
  menuTextColor: {
    type: 'string'
  },
  menuHoverTextColor: {
    type: 'string'
  },
  menuBackgroundColor: {
    type: 'string'
  },
  menuHoverBackgroundColor: {
    type: 'string'
  },
  submenuTextColor: {
    type: 'string'
  },
  submenuHoverTextColor: {
    type: 'string'
  },
  submenuBackgroundColor: {
    type: 'string'
  },
  submenuHoverBackgroundColor: {
    type: 'string'
  },
  menuSeparatorColor: {
    type: 'string'
  }
};
/**
 * Add spacing control attribute to block.
 *
 * @param {object} settings Current block settings.
 * @param {string} name Name of block.
 *
 * @returns {object} Modified block settings.
 */

const addSpacingControlAttribute = (settings, name) => {
  // Do nothing if it's another block than our defined ones.
  if (!enableSpacingControlOnBlocks.includes(name)) {
    return settings;
  }

  console.log(settings.attributes); // Use Lodash's assign to gracefully handle if attributes are undefined

  settings.attributes = Object.assign(settings.attributes, {
    spacing: {
      type: 'string',
      default: spacingControlOptions[0].value
    }
  }, maxMegaMenuAttributes);
  return settings;
};

addFilter('blocks.registerBlockType', 'extend-block-example/attribute/spacing', addSpacingControlAttribute);
/**
 * Create HOC to add spacing control to inspector controls of block.
 */

const withSpacingControl = createHigherOrderComponent(BlockEdit => {
  return props => {
    // Do nothing if it's another block than our defined ones.
    if (!enableSpacingControlOnBlocks.includes(props.name)) {
      return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, props);
    }

    const {
      menuTextColor,
      menuHoverTextColor,
      menuBackgroundColor,
      menuHoverBackgroundColor,
      submenuTextColor,
      submenuHoverTextColor,
      submenuBackgroundColor,
      submenuHoverBackgroundColor,
      menuSeparatorColor
    } = props.attributes;
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, props), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(InspectorControls, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(PanelBody, {
      title: __('Blaze Commerce - Colors'),
      initialOpen: true
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Link"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: menuTextColor,
      setValue: selectedColor => props.setAttributes({
        menuTextColor: selectedColor
      })
    })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: menuHoverTextColor,
      setValue: selectedColor => props.setAttributes({
        menuHoverTextColor: selectedColor
      })
    }))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Background"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: menuBackgroundColor,
      setValue: selectedColor => props.setAttributes({
        menuBackgroundColor: selectedColor
      })
    })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: menuHoverBackgroundColor,
      setValue: selectedColor => props.setAttributes({
        menuHoverBackgroundColor: selectedColor
      })
    }))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Submenu Text"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: submenuTextColor,
      setValue: selectedColor => props.setAttributes({
        submenuTextColor: selectedColor
      })
    })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: submenuHoverTextColor,
      setValue: selectedColor => props.setAttributes({
        submenuHoverTextColor: selectedColor
      })
    }))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Submenu Background"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: submenuBackgroundColor,
      setValue: selectedColor => props.setAttributes({
        submenuBackgroundColor: selectedColor
      })
    })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: submenuHoverBackgroundColor,
      setValue: selectedColor => props.setAttributes({
        submenuHoverBackgroundColor: selectedColor
      })
    }))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Divider, null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Separator Color"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
      value: menuSeparatorColor,
      setValue: selectedColor => props.setAttributes({
        menuSeparatorColor: selectedColor
      })
    }))))));
  };
}, 'withSpacingControl');
addFilter('editor.BlockEdit', 'extend-block-example/with-spacing-control', withSpacingControl);
/**
 * Add margin style attribute to save element of block.
 *
 * @param {object} saveElementProps Props of save element.
 * @param {Object} blockType Block type information.
 * @param {Object} attributes Attributes of block.
 *
 * @returns {object} Modified props of save element.
 */

const addSpacingExtraProps = (saveElementProps, blockType, attributes) => {
  // Do nothing if it's another block than our defined ones.
  if (!enableSpacingControlOnBlocks.includes(blockType.name)) {
    return saveElementProps;
  }

  const margins = {
    small: '5px',
    medium: '15px',
    large: '30px'
  };

  if (attributes.spacing in margins) {
    // Use Lodash's assign to gracefully handle if attributes are undefined
    saveElementProps = Object.assign(saveElementProps, {
      style: {
        'margin-bottom': margins[attributes.spacing]
      }
    });
  }

  return saveElementProps;
};

addFilter('blocks.getSaveContent.extraProps', 'extend-block-example/get-save-content/extra-props', addSpacingExtraProps);
}();
/******/ })()
;
//# sourceMappingURL=index.js.map