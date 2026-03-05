import Headroom from "headroom.js";
import { debounce } from "@js/parts/debounce";

export function pageHeader(header) {
      // Variables
      let navsWrapper = document.querySelector('.Header-navs');
      let toggle = header.querySelector(".js-toggle");

      // Open the mobile navigation
      if (toggle && header && navsWrapper) {

            // Init headroom
            var headroom = new Headroom(header, {
                  offset: 75
            });
            headroom.init();

            // // Add body padding
            // document.querySelector("body").style.paddingTop = (header.offsetHeight) + 'px';
            // window.addEventListener("resize", (e) => {
            //       document.querySelector("body").style.paddingTop = (header.offsetHeight) + 'px';
            // })

            // Toggle navigation
            toggle.addEventListener("click", (e) => {
                  // First click class
                  header.classList.add("first-click");

                  // Toggle nav show/hide
                  header.classList.toggle("show-nav");

                  // Add top padding + fixed height
                  navsWrapper.style.paddingTop = (header.offsetHeight) + "px";
                  navsWrapper.style.height = (window.innerHeight) + 'px';

                  // Close nav in escape key
                  document.addEventListener('keydown', function (event) {
                        if ((event.key === 'Escape' || event.key === 'Esc') && header.classList.contains('show-nav')) { // For older browsers that might still use 'Esc'
                              console.log('123');
                              header.classList.remove("show-nav");
                        }
                  });

                  // On resize -> reset height
                  if (header.classList.contains('show-nav')) {
                        window.addEventListener("resize", debounce(function (e) {
                              if (window.innerWidth < 1024) {
                                    navsWrapper.style.height = (window.innerHeight) + 'px';
                              }
                        }));
                  }
            });
      }
}