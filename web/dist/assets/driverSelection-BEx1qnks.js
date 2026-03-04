/**
 * name: craftskerm
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
function u(s){const o=s.dataset.raceId,c=s.dataset.csrf,i=s.dataset.submitUrl;if(!(s.dataset.isPlayerTurn==="1"))return;const t=s.querySelectorAll(".F1DriverCard:not(.is-disabled)");t.forEach(e=>{e.addEventListener("click",async()=>{const d=e.dataset.driverId,n=e.querySelector(".font-black")?.textContent?.trim();if(confirm(`Select ${n} for P10?`)){t.forEach(a=>a.classList.add("is-disabled")),e.classList.add("is-selected");try{const r=await(await fetch(i,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded",Accept:"application/json"},body:new URLSearchParams({CRAFT_CSRF_TOKEN:c,raceId:o,driverId:d})})).json();r.success?window.location.reload():(alert(r.error||"Failed to submit prediction"),t.forEach(l=>l.classList.remove("is-disabled")),e.classList.remove("is-selected"))}catch{alert("Network error. Please try again."),t.forEach(r=>r.classList.remove("is-disabled")),e.classList.remove("is-selected")}}})})}export{u as driverSelection};
