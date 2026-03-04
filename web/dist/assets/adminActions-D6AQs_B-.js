/**
 * name: f1gooat
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
function l(){const s=document.querySelectorAll(".js-admin-action"),n=document.querySelector("#adminMessage");if(!s.length)return;function a(t,o=!1){n.textContent=t,n.className=`text-sm ${o?"text-primary":"text-f1-success"}`,n.classList.remove("hidden")}s.forEach(t=>{t.addEventListener("click",async()=>{const o=t.dataset.url,c=t.dataset.csrf,i=t.textContent.trim();s.forEach(e=>{e.disabled=!0,e.classList.add("opacity-50","pointer-events-none")}),a(`${i}...`);try{const r=await(await fetch(o,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded",Accept:"application/json"},body:new URLSearchParams({CRAFT_CSRF_TOKEN:c})})).json();r.success?a(r.message):a(r.error||"Something went wrong.",!0)}catch{a("Network error. Please try again.",!0)}finally{s.forEach(e=>{e.disabled=!1,e.classList.remove("opacity-50","pointer-events-none")})}})})}export{l as adminActions};
