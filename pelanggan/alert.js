// ---------------------------------------------
//  CUSTOM ALERT
// ---------------------------------------------
function showAlert(message, title = "Informasi") {
    createOverlay();

    const box = document.createElement("div");
    box.className = "alert-box";
    box.innerHTML = `
        <h3 class="alert-title">${title}</h3>
        <p class="alert-message">${message}</p>
        <button class="alert-btn">OK</button>
    `;

    document.body.appendChild(box);

    box.querySelector(".alert-btn").onclick = () => {
        closeOverlay();
        box.remove();
    };
}

// ---------------------------------------------
//  CUSTOM CONFIRM
// ---------------------------------------------
function showConfirm(message, onOk, title = "Konfirmasi") {
    createOverlay();

    const box = document.createElement("div");
    box.className = "confirm-box";
    box.innerHTML = `
        <h3 class="alert-title">${title}</h3>
        <p class="alert-message">${message}</p>
        <div class="confirm-actions">
            <button class="confirm-cancel">Batal</button>
            <button class="confirm-ok">OK</button>
        </div>
    `;

    document.body.appendChild(box);

    box.querySelector(".confirm-cancel").onclick = () => {
        closeOverlay();
        box.remove();
    };

    box.querySelector(".confirm-ok").onclick = () => {
        closeOverlay();
        box.remove();
        if (typeof onOk === "function") onOk();
    };
}

// ---------------------------------------------
//  OVERLAY MANAGER
// ---------------------------------------------
function createOverlay() {
    const overlay = document.createElement("div");
    overlay.id = "alert-overlay";
    overlay.className = "alert-overlay";
    document.body.appendChild(overlay);
}

function closeOverlay() {
    const overlay = document.getElementById("alert-overlay");
    if (overlay) overlay.remove();
}
