// import React from 'react';
// import ReactDOM from 'react-dom';
// import { App } from './App';
// import { Provider } from 'react-redux';
// import { store } from './store'

import { BlazeWooless } from "./blaze-wooless";

// ReactDOM.render(<Provider store={store}>
//   <App />
// </Provider>, document.getElementById('my-plugin-root'));
(($) => {
  console.log('from es6', $);
  $(document).ready(function() {
    BlazeWooless.init();
  });
})(jQuery)
