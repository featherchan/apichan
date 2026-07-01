import { createApp } from "vue";
import "../style.css";
import SourcesManager from "../pages/SourcesManager.vue";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(SourcesManager);

app.use(Toast, {
  transition: "Vue-Toastification__bounce",
  maxToasts: 20,
  newestOnTop: true,
});

function applyTheme(theme: "light" | "dark") {
  document.documentElement.classList.toggle("dark", theme === "dark");
}

window.addEventListener("message", (event) => {
  if (event.data?.type === "featherpanel-theme") {
    applyTheme(event.data.theme);
  }
});

if (window.parent !== window) {
  window.parent.postMessage({ type: "featherpanel-ready" }, "*");
}

applyTheme("dark");
document.body.style.background = "transparent";
document.documentElement.style.background = "transparent";

app.mount("#app");
