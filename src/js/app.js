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

      // Admin actions (footer sync buttons)
      const adminButtons = document.querySelectorAll('.js-admin-action');
      if (adminButtons.length > 0) {
            const { adminActions } = await import('@js/parts/adminActions');
            adminActions();
      }
})