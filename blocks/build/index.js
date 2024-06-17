/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/element-color-selector.js":
/*!**************************************************!*\
  !*** ./src/components/element-color-selector.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ElementColorSelector: () => (/* binding */ ElementColorSelector)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

const {
  useState
} = wp.element;
const {
  ColorPicker,
  Popover,
  Button
} = wp.components;
const ElementColorSelector = ({
  value,
  setValue
}) => {
  const [isVisible, setIsVisible] = useState(false);
  const [selectedColor, setSelectedColor] = useState(value);
  const [popoverAnchor, setPopoverAnchor] = useState();
  console.log('rerendering?');
  const showColorPicker = () => {
    console.log('clicked wow');
    setIsVisible(true);
  };
  const hideColorPicker = () => {
    setIsVisible(false);
  };
  const saveColor = () => {
    setValue(selectedColor);
    hideColorPicker();
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ref: setPopoverAnchor,
    onClick: showColorPicker,
    style: {
      width: '20px',
      height: '20px',
      borderRadius: '9999px',
      backgroundColor: value,
      cursor: 'pointer',
      border: '1px solid #e0e0e0'
    }
  }), isVisible && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Popover, {
    anchor: popoverAnchor,
    placement: "bottom-end",
    position: "top left"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ColorPicker, {
    color: selectedColor,
    onChange: setSelectedColor,
    enableAlpha: true,
    defaultValue: "#000"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: 'flex',
      padding: '0 16px 20px',
      justifyContent: 'flex-end',
      gap: '10px'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Button, {
    variant: "secondary",
    size: "compact",
    onClick: hideColorPicker
  }, "Cancel"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Button, {
    variant: "primary",
    size: "compact",
    onClick: saveColor
  }, "Save"))));
};

/***/ }),

/***/ "./src/components/maxmegamenu/color-config.js":
/*!****************************************************!*\
  !*** ./src/components/maxmegamenu/color-config.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ColorConfig: () => (/* binding */ ColorConfig)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _element_color_selector__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../element-color-selector */ "./src/components/element-color-selector.js");


const {
  PanelBody,
  __experimentalDivider: Divider,
  Flex,
  FlexBlock,
  FlexItem
} = wp.components;
const {
  __
} = wp.i18n;
const ColorConfig = ({
  attributes,
  setAttributes
}) => {
  const {
    mainNavigationBackgroundColor,
    menuTextColor,
    menuHoverTextColor,
    menuBackgroundColor,
    menuHoverBackgroundColor,
    submenuTextColor,
    submenuHoverTextColor,
    submenuBackgroundColor,
    submenuHoverBackgroundColor,
    menuSeparatorColor
  } = attributes;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PanelBody, {
    title: __('Blaze Commerce - Colors'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Main Navigation Background Color"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: mainNavigationBackgroundColor,
    setValue: selectedColor => setAttributes({
      mainNavigationBackgroundColor: selectedColor
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Divider, null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Text"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: menuTextColor,
    setValue: selectedColor => setAttributes({
      menuTextColor: selectedColor
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: menuHoverTextColor,
    setValue: selectedColor => setAttributes({
      menuHoverTextColor: selectedColor
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Background"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: menuBackgroundColor,
    setValue: selectedColor => setAttributes({
      menuBackgroundColor: selectedColor
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: menuHoverBackgroundColor,
    setValue: selectedColor => setAttributes({
      menuHoverBackgroundColor: selectedColor
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Submenu Text"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: submenuTextColor,
    setValue: selectedColor => setAttributes({
      submenuTextColor: selectedColor
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: submenuHoverTextColor,
    setValue: selectedColor => setAttributes({
      submenuHoverTextColor: selectedColor
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Submenu Background"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: submenuBackgroundColor,
    setValue: selectedColor => setAttributes({
      submenuBackgroundColor: selectedColor
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: submenuHoverBackgroundColor,
    setValue: selectedColor => setAttributes({
      submenuHoverBackgroundColor: selectedColor
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Divider, null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Flex, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexBlock, null, "Menu Separator Color"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_element_color_selector__WEBPACK_IMPORTED_MODULE_1__.ElementColorSelector, {
    value: menuSeparatorColor,
    setValue: selectedColor => setAttributes({
      menuSeparatorColor: selectedColor
    })
  }))));
};

/***/ }),

/***/ "./src/components/maxmegamenu/layout-config.js":
/*!*****************************************************!*\
  !*** ./src/components/maxmegamenu/layout-config.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   LayoutConfig: () => (/* binding */ LayoutConfig)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

const {
  PanelBody,
  ToggleControl
} = wp.components;
const {
  __
} = wp.i18n;
const LayoutConfig = ({
  attributes,
  setAttributes
}) => {
  const {
    menuCentered,
    menuFullWidth
  } = attributes;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PanelBody, {
    title: __('Blaze Commerce - Layout'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ToggleControl, {
    label: "Centered",
    help: menuCentered ? 'Menu is centered.' : 'Menu starts on the left.',
    checked: menuCentered,
    onChange: newValue => {
      setAttributes({
        menuCentered: newValue
      });
    }
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ToggleControl, {
    label: "Full Width",
    help: menuCentered ? 'Menu is full width.' : 'Menu width is auto.',
    checked: menuFullWidth,
    onChange: newValue => {
      setAttributes({
        menuFullWidth: newValue
      });
    }
  }));
};

/***/ }),

/***/ "./src/components/maxmegamenu/spacing-config.js":
/*!******************************************************!*\
  !*** ./src/components/maxmegamenu/spacing-config.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SpacingConfig: () => (/* binding */ SpacingConfig)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

const {
  PanelBody,
  __experimentalBoxControl: BoxControl
} = wp.components;
const {
  __
} = wp.i18n;
const SpacingConfig = ({
  attributes,
  setAttributes
}) => {
  const {
    menuTextPadding,
    menuTextMargin,
    submenuTextPadding,
    submenuTextMargin
  } = attributes;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PanelBody, {
    title: __('Blaze Commerce - Spacing'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BoxControl, {
    label: __('Menu Text Padding'),
    values: menuTextPadding,
    onChange: nextValues => setAttributes({
      menuTextPadding: nextValues
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BoxControl, {
    label: __('Menu Text Margin'),
    values: menuTextMargin,
    onChange: nextValues => setAttributes({
      menuTextMargin: nextValues
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BoxControl, {
    label: __('Submenu Text Padding'),
    values: submenuTextPadding,
    onChange: nextValues => setAttributes({
      submenuTextPadding: nextValues
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BoxControl, {
    label: __('Submenu Text Margin'),
    values: submenuTextMargin,
    onChange: nextValues => setAttributes({
      submenuTextMargin: nextValues
    })
  }));
};

/***/ }),

/***/ "./src/components/maxmegamenu/typography-config.js":
/*!*********************************************************!*\
  !*** ./src/components/maxmegamenu/typography-config.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TypographyConfig: () => (/* binding */ TypographyConfig)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

const {
  useState
} = wp.element;
const {
  PanelBody,
  FontSizePicker
} = wp.components;
// const { FontSizePicker } = wp.editor;
const {
  __
} = wp.i18n;
const fontSizes = [{
  name: __('Small'),
  slug: 'small',
  size: 12
}, {
  name: __('Medium'),
  slug: 'medium',
  size: 18
}];
const fallbackFontSize = 16;
const TypographyConfig = ({
  attributes,
  setAttributes
}) => {
  const [fontSize, setFontSize] = useState(12);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(PanelBody, {
    title: __('Blaze Commerce - Typography'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FontSizePicker, {
    fontSizes: fontSizes,
    value: fontSize,
    fallbackFontSize: fallbackFontSize,
    onChange: newFontSize => {
      setFontSize(newFontSize);
    }
  }));
};

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

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
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_maxmegamenu_color_config__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/maxmegamenu/color-config */ "./src/components/maxmegamenu/color-config.js");
/* harmony import */ var _components_maxmegamenu_layout_config__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/maxmegamenu/layout-config */ "./src/components/maxmegamenu/layout-config.js");
/* harmony import */ var _components_maxmegamenu_spacing_config__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/maxmegamenu/spacing-config */ "./src/components/maxmegamenu/spacing-config.js");
/* harmony import */ var _components_maxmegamenu_typography_config__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/maxmegamenu/typography-config */ "./src/components/maxmegamenu/typography-config.js");





const {
  createHigherOrderComponent
} = wp.compose;
const {
  Fragment
} = wp.element;
const {
  InspectorControls
} = wp.editor;
const {
  addFilter
} = wp.hooks;

// Enable spacing control on the following blocks
const enableSpacingControlOnBlocks = ['maxmegamenu/location'];
const boxControlDefaults = {
  top: '0px',
  left: '0px',
  right: '0px',
  bottom: '0px'
};
const menuAttributes = {
  mainNavigationBackgroundColor: {
    type: 'string'
  },
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
  },
  menuTextPadding: {
    type: 'object',
    default: boxControlDefaults
  },
  menuTextMargin: {
    type: 'object',
    default: boxControlDefaults
  },
  submenuTextPadding: {
    type: 'object',
    default: boxControlDefaults
  },
  submenuTextMargin: {
    type: 'object',
    default: boxControlDefaults
  },
  menuCentered: {
    type: 'boolean'
  },
  menuFullWidth: {
    type: 'boolean'
  },
  fontSize: {
    tyupe: 'string'
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
const addMenuAttributes = (settings, name) => {
  // Do nothing if it's another block than our defined ones.
  if (!enableSpacingControlOnBlocks.includes(name)) {
    return settings;
  }

  // Use Lodash's assign to gracefully handle if attributes are undefined
  settings.attributes = Object.assign(settings.attributes, menuAttributes);
  settings.supports = Object.assign(settings.supports, {
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalFontFamily": true,
      "__experimentalFontWeight": true,
      "__experimentalFontStyle": true,
      "__experimentalTextTransform": true,
      "__experimentalTextDecoration": true,
      "__experimentalLetterSpacing": true,
      "__experimentalDefaultControls": {
        "fontSize": true
      }
    }
  });
  return settings;
};
addFilter('blocks.registerBlockType', 'extend-block-example/attribute/spacing', addMenuAttributes);

/**
 * Create HOC to add spacing control to inspector controls of block.
 */
const withSpacingControl = createHigherOrderComponent(BlockEdit => {
  return props => {
    console.log('props', props);
    // Do nothing if it's another block than our defined ones.
    if (!enableSpacingControlOnBlocks.includes(props.name)) {
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, {
        ...props
      });
    }
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, {
      ...props
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_maxmegamenu_layout_config__WEBPACK_IMPORTED_MODULE_2__.LayoutConfig, {
      ...props
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_maxmegamenu_spacing_config__WEBPACK_IMPORTED_MODULE_3__.SpacingConfig, {
      ...props
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_maxmegamenu_color_config__WEBPACK_IMPORTED_MODULE_1__.ColorConfig, {
      ...props
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_maxmegamenu_typography_config__WEBPACK_IMPORTED_MODULE_4__.TypographyConfig, {
      ...props
    })));
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
/******/ })()
;
//# sourceMappingURL=index.js.map