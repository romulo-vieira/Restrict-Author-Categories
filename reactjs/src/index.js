import 'react-app-polyfill/ie11'; // Polyfill import must be the first line in src/index.js
import 'react-app-polyfill/stable';

import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';

window.addEventListener('load', (e)=>{

  ReactDOM.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
    document.getElementById('resautcat-content-container')
  );

  // show app only when page is fully loaded (it's needed because WP has a extremely lazy
  // css loader that will make the app looks terrible while not fully loaded)
  document.querySelector('#resautcat-page-admin').style.opacity = 1;
});