import { gsap } from 'gsap';

export function playerRaceByRace(playerRaceByRace) {
      const playerInfo = playerRaceByRace.querySelector('.js-player--info');
      const playerRaceByRaceInfo = playerRaceByRace.querySelector('.js-player--details');
      const playerArrow = playerRaceByRace.querySelector('.js-player--arrow');


      let isOpen = false;

      playerInfo.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;

            isOpen = !isOpen;

            gsap.to(playerRaceByRaceInfo, {
                  height: isOpen ? 'auto' : 0,
                  opacity: isOpen ? 1 : 0,
                  duration: .8
            });

            gsap.to(playerArrow, {
                  rotation: isOpen ? 180 : 0,
                  duration: .4
            });
      });
}