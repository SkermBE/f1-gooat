/**
 * name: craftskerm
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
function l(){const s=document.querySelectorAll(".js-admin-action"),a=document.querySelector("#adminMessage");if(!s.length)return;function n(t,o=!1){a.textContent=t,a.className=`text-sm ${o?"text-red-400":"text-green-400"}`,a.classList.remove("hidden")}s.forEach(t=>{t.addEventListener("click",async()=>{const o=t.dataset.url,c=t.dataset.csrf,i=t.textContent.trim();s.forEach(e=>{e.disabled=!0,e.classList.add("opacity-50","pointer-events-none")}),n(`${i}...`);try{const r=await(await fetch(o,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded",Accept:"application/json"},body:new URLSearchParams({CRAFT_CSRF_TOKEN:c})})).json();r.success?n(r.message):n(r.error||"Something went wrong.",!0)}catch{n("Network error. Please try again.",!0)}finally{s.forEach(e=>{e.disabled=!1,e.classList.remove("opacity-50","pointer-events-none")})}})})}export{l as adminActions};
