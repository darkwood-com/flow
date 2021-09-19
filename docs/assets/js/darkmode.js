const mode = document.getElementById('mode');

if (mode !== null) {

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'dark');
      document.documentElement.setAttribute('data-dark-mode', '');

    } else {

      localStorage.setItem('theme', 'light');
      document.documentElement.setAttribute('data-light-mode', '');

    }

  })

  window.matchMedia('(prefers-color-scheme: sepia)').addEventListener('change', event => {

    if (event.matches) {

      localStorage.setItem('theme', 'sepia');
      document.documentElement.setAttribute('data-sepia-mode', '');

    } else {

      localStorage.setItem('theme', 'light');
      document.documentElement.setAttribute('data-light-mode', '');

    }

  })

  mode.addEventListener('click', () => {

    var isDark = document.documentElement.hasAttribute('data-dark-mode')
    var isSepia = document.documentElement.hasAttribute('data-sepia-mode')
    var isLight = document.documentElement.hasAttribute('data-light-mode')
    if(isDark) {
      localStorage.setItem('theme', 'sepia');
    }
    if(isSepia) {
      localStorage.setItem('theme', 'light');
    }
    if(isLight) {
      localStorage.setItem('theme', 'dark');
    }

    document.documentElement.removeAttribute('data-dark-mode');
    document.documentElement.removeAttribute('data-sepia-mode');
    document.documentElement.removeAttribute('data-light-mode');
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.setAttribute('data-dark-mode', '');
    } else if(localStorage.getItem('theme') === 'sepia') {
      document.documentElement.setAttribute('data-sepia-mode', '');
    } else if(localStorage.getItem('theme') === 'light') {
      document.documentElement.setAttribute('data-light-mode', '');
    }
  });

  document.documentElement.removeAttribute('data-dark-mode');
  document.documentElement.removeAttribute('data-sepia-mode');
  document.documentElement.removeAttribute('data-light-mode');
  if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-dark-mode', '');
  } else if(localStorage.getItem('theme') === 'sepia') {
    document.documentElement.setAttribute('data-sepia-mode', '');
  } else if(localStorage.getItem('theme') === 'light') {
    document.documentElement.setAttribute('data-light-mode', '');
  }

}
