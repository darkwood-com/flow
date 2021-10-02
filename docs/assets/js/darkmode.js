const mode = document.getElementById('mode');
const body = document.querySelector('body')

if (mode !== null) {

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'dark');
      body.setAttribute('data-dark-mode', 'true');

    } else {

      localStorage.setItem('theme', 'light');
      body.setAttribute('data-light-mode', 'true');

    }

  })

  window.matchMedia('(prefers-color-scheme: sepia)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'sepia');
      body.setAttribute('data-sepia-mode', 'true');

    } else {

      localStorage.setItem('theme', 'light');
      body.setAttribute('data-light-mode', 'true');

    }

  })

  mode.addEventListener('click', () => {

    var isDark = body.hasAttribute('data-dark-mode')
    var isSepia = body.hasAttribute('data-sepia-mode')
    var isLight = body.hasAttribute('data-light-mode')
    if(isDark) {
      localStorage.setItem('theme', 'sepia');
    }
    if(isSepia) {
      localStorage.setItem('theme', 'light');
    }
    if(isLight) {
      localStorage.setItem('theme', 'dark');
    }

    body.removeAttribute('data-dark-mode');
    body.removeAttribute('data-sepia-mode');
    body.removeAttribute('data-light-mode');
    if (localStorage.getItem('theme') === 'dark') {
      body.setAttribute('data-dark-mode', 'true');
    } else if(localStorage.getItem('theme') === 'sepia') {
      body.setAttribute('data-sepia-mode', 'true');
    } else if(localStorage.getItem('theme') === 'light') {
      body.setAttribute('data-light-mode', 'true');
    }
  });

  body.removeAttribute('data-dark-mode');
  body.removeAttribute('data-sepia-mode');
  body.removeAttribute('data-light-mode');
  if (localStorage.getItem('theme') === 'dark') {
    body.setAttribute('data-dark-mode', 'true');
  } else if(localStorage.getItem('theme') === 'sepia') {
    body.setAttribute('data-sepia-mode', 'true');
  } else if(localStorage.getItem('theme') === 'light') {
    body.setAttribute('data-light-mode', 'true');
  }

}
