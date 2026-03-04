/**
 * name: craftskerm
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
function l(i){const t=document.createElement("div"),e=document.createElement("div"),n=document.createElement("div"),s=document.createElement("div"),d=document.createElement("div"),o=document.createElement("div");t.appendChild(e),e.appendChild(n),e.appendChild(s),e.appendChild(d),e.appendChild(o),document.documentElement.appendChild(t),t.classList.add("design-grid--toggle"),e.classList.add("design-grid--toggle-icon"),n.classList.add("design-grid--toggle-icon--line1"),s.classList.add("design-grid--toggle-icon--line2"),d.classList.add("design-grid--toggle-icon--line3"),o.classList.add("design-grid--toggle-icon--line4"),sessionStorage.getItem("designgrid")=="show"&&i.classList.remove("not-visible"),t.addEventListener("click",()=>{i.classList.contains("not-visible")?(sessionStorage.setItem("designgrid","show"),i.classList.remove("not-visible")):(sessionStorage.setItem("designgrid","hide"),i.classList.add("not-visible"))})}export{l as designGrid};
