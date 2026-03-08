// Import css
import '../css/app.css';

// Formie forms
import { formieForm } from "@js/parts/formie";
formieForm();

document.addEventListener('DOMContentLoaded', async () => {
      // Design grid
      const designGridElement = document.querySelector('.design-grid');
      if (designGridElement) {
            const { designGrid } = await import('@js/parts/design-grid');
            designGrid(designGridElement);
      }    
      
      // A11y dialogs

      const popUps = document.querySelectorAll('.js-dialog');
      if (popUps.length > 0) {
            const { a11yDialog } = await import('@js/parts/a11y-dialog');
            popUps.forEach(popUp => a11yDialog(popUp));
      }
      
      // Page navigation
      let headerElement = document.querySelector('#Header');
      if (headerElement) {
            const { pageHeader } = await import('@js/parts/pageHeader');
            pageHeader(headerElement);
      }

      // Driver selection grid
      const driverGrid = document.querySelector('#driverGrid');
      if (driverGrid) {
            const { driverSelection } = await import('@js/parts/driverSelection');
            driverSelection(driverGrid);
      }

      // Season chart
      const seasonChartCanvas = document.querySelector('#seasonChart');
      if (seasonChartCanvas) {
            const { seasonChart } = await import('@js/parts/seasonChart');
            seasonChart(seasonChartCanvas);
      }

      // Skip player button
      const skipPlayerBtn = document.querySelector('#skipPlayerBtn');
      if (skipPlayerBtn) {
            const { skipPlayer } = await import('@js/parts/skipPlayer');
            skipPlayer(skipPlayerBtn);
      }

      // Re-fetch race results
      const refetchBtn = document.querySelector('.js-refetch-results');
      if (refetchBtn) {
            const { refetchResults } = await import('@js/parts/refetchResults');
            refetchResults(refetchBtn);
      }

      // Admin actions (footer sync buttons)
      const adminButtons = document.querySelectorAll('.js-admin-action');
      if (adminButtons.length > 0) {
            const { adminActions } = await import('@js/parts/adminActions');
            adminActions();
      }
 
      // Race by race detail
      const playersRaceByRaceElements = document.querySelectorAll('.js-player');
      if (playersRaceByRaceElements.length > 0) {
            const { playerRaceByRace } = await import('@js/parts/playerRaceByRace');
            playersRaceByRaceElements.forEach(playersRaceByRaceElement => playerRaceByRace(playersRaceByRaceElement));
      }
})