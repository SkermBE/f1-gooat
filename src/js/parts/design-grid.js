export function designGrid($designGrid) {
      const $toggle = document.createElement('div');
      const $toggleIcon = document.createElement('div');
      const $toggleIconLine1 = document.createElement('div');
      const $toggleIconLine2 = document.createElement('div');
      const $toggleIconLine3 = document.createElement('div');
      const $toggleIconLine4 = document.createElement('div');

      $toggle.appendChild($toggleIcon);
      $toggleIcon.appendChild($toggleIconLine1);
      $toggleIcon.appendChild($toggleIconLine2);
      $toggleIcon.appendChild($toggleIconLine3);
      $toggleIcon.appendChild($toggleIconLine4);
      document.documentElement.appendChild($toggle);

      $toggle.classList.add('design-grid--toggle');
      $toggleIcon.classList.add('design-grid--toggle-icon');
      $toggleIconLine1.classList.add('design-grid--toggle-icon--line1');
      $toggleIconLine2.classList.add('design-grid--toggle-icon--line2');
      $toggleIconLine3.classList.add('design-grid--toggle-icon--line3');
      $toggleIconLine4.classList.add('design-grid--toggle-icon--line4');

      if (sessionStorage.getItem('designgrid') == 'show') {
            $designGrid.classList.remove('not-visible');
      }

      $toggle.addEventListener('click', () => {
            if ($designGrid.classList.contains('not-visible')) {
                  sessionStorage.setItem('designgrid', 'show');
                  $designGrid.classList.remove('not-visible');
            } else {
                  sessionStorage.setItem('designgrid', 'hide');
                  $designGrid.classList.add('not-visible');
            }
      })
}