(() => {
    const MESSAGE = "No est\u00e1s conectado a Internet";

    const getNetworkBanner = () => {
        let banner = document.getElementById("network-status-banner");

        if (banner) {
            return banner;
        }

        banner = document.createElement("div");
        banner.id = "network-status-banner";
        banner.className = "network-status-banner";
        banner.setAttribute("role", "alert");
        banner.setAttribute("aria-live", "assertive");
        banner.setAttribute("aria-atomic", "true");
        banner.textContent = MESSAGE;
        banner.hidden = true;
        document.body.appendChild(banner);

        return banner;
    };

    const updateNetworkStatus = () => {
        getNetworkBanner().hidden = navigator.onLine;
    };

    const initializeNetworkStatus = () => {
        updateNetworkStatus();
        window.addEventListener("offline", updateNetworkStatus);
        window.addEventListener("online", updateNetworkStatus);
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeNetworkStatus, { once: true });
    } else {
        initializeNetworkStatus();
    }

    if ("serviceWorker" in navigator) {
        window.addEventListener("load", () => {
            navigator.serviceWorker.register("/service-worker.js", { scope: "/" }).catch(error => {
                console.error("No se pudo activar el modo PWA.", error);
            });
        });
    }
})();
