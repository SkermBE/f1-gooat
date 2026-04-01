import { gsap } from 'gsap';

const currentPlayerId = document.body.dataset.currentPlayer;

export function playerRaceByRace(playerRaceByRace) {
      if (currentPlayerId && playerRaceByRace.dataset.playerId === currentPlayerId) {
            playerRaceByRace.querySelector('.js-player--name')
                  .insertAdjacentHTML('beforeend', '<span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-green-500 text-white rounded-full">You</span>');
      }

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