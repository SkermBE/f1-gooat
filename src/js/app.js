// Import css
import '../css/app.css';

// Static imports — used on every page, avoids waterfall chain
import { formieForm } from "@js/parts/formie";
import { pageHeader } from "@js/parts/pageHeader";

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

      // Page navigation (static import — present on every page)
      let headerElement = document.querySelector('#Header');
      if (headerElement) {
            pageHeader(headerElement);
      }

      // Fire remaining dynamic imports in parallel (no await chain)
      const dynamicInits = [];

      // Driver selection grid
      const driverGrid = document.querySelector('#driverGrid');
      if (driverGrid) {
            dynamicInits.push(import('@js/parts/driverSelection').then(m => m.driverSelection(driverGrid)));
      }

      // Season chart
      const seasonChartCanvas = document.querySelector('#seasonChart');
      if (seasonChartCanvas) {
            dynamicInits.push(import('@js/parts/seasonChart').then(m => m.seasonChart(seasonChartCanvas)));
      }

      // Skip player button
      const skipPlayerBtn = document.querySelector('#skipPlayerBtn');
      if (skipPlayerBtn) {
            dynamicInits.push(import('@js/parts/skipPlayer').then(m => m.skipPlayer(skipPlayerBtn)));
      }

      // Re-fetch race results
      const refetchBtn = document.querySelector('.js-refetch-results');
      if (refetchBtn) {
            dynamicInits.push(import('@js/parts/refetchResults').then(m => m.refetchResults(refetchBtn)));
      }

      // Admin actions (footer sync buttons)
      const adminButtons = document.querySelectorAll('.js-admin-action');
      if (adminButtons.length > 0) {
            dynamicInits.push(import('@js/parts/adminActions').then(m => m.adminActions()));
      }

      // Race by race detail
      const playersRaceByRaceElements = document.querySelectorAll('.js-player');
      if (playersRaceByRaceElements.length > 0) {
            dynamicInits.push(import('@js/parts/playerRaceByRace').then(m => {
                  playersRaceByRaceElements.forEach(el => m.playerRaceByRace(el));
            }));
      }

      await Promise.all(dynamicInits);
})