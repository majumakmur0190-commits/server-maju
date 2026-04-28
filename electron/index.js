const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

// Path untuk file konfigurasi JSON
const configPath = path.join(__dirname, 'config.json');

// Handler untuk memuat konfigurasi dari file JSON
ipcMain.handle('load-config', () => {
    try {
        if (fs.existsSync(configPath)) {
            const data = fs.readFileSync(configPath, 'utf8');
            return JSON.parse(data);
        }
    } catch (error) {
        console.error("Gagal membaca config.json:", error);
    }
    return {};
});

// Handler untuk menyimpan konfigurasi ke file JSON
ipcMain.handle('save-config', (event, newConfig) => {
    try {
        let existingConfig = {};
        if (fs.existsSync(configPath)) {
            const data = fs.readFileSync(configPath, 'utf8');
            existingConfig = JSON.parse(data);
        }
        const mergedConfig = { ...existingConfig, ...newConfig };
        fs.writeFileSync(configPath, JSON.stringify(mergedConfig, null, 2));
        return true;
    } catch (error) {
        console.error("Gagal menyimpan config.json:", error);
        return false;
    }
});



// Handle creating/removing shortcuts on Windows when installing/uninstalling.
if (require('electron-squirrel-startup')) {
    app.quit();
}

let mainWindow;

function createWindow() {
    // If window already exists, just return
    if (mainWindow) return;

    // Membuat jendela browser.
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        fullscreen: true,
        autoHideMenuBar: true,
        icon: path.join(__dirname, 'gambar/logo.png'), // Menambahkan path ke ikon
        webPreferences: {
            // Disarankan untuk menjaga keamanan ini bahkan saat memuat localhost
            contextIsolation: true,
            nodeIntegration: false,
            preload: path.join(__dirname, 'preload.js')
        },
    });

    // Di Main Process (main.js)
    ipcMain.handle('get-printers', async () => {
        const printers = await mainWindow.webContents.getPrintersAsync();
        console.log("Printers found:", printers.length);
        return printers;
    });

    ipcMain.on('print-url', (event, url, printerName) => {
        const workerWindow = new BrowserWindow({ 
            show: false,
            width: 800, // Berikan dimensi agar layout ter-render
            height: 600,
            webPreferences: {
                nodeIntegration: false,
                contextIsolation: true
            }
        });

        workerWindow.loadURL(url);

        workerWindow.webContents.on('did-finish-load', () => {
            // Gunakan timeout singkat untuk memastikan konten dinamis/CSS ter-render
            setTimeout(() => {
                workerWindow.webContents.print({
                    silent: true,
                    deviceName: printerName
                }, (success, failureReason) => {
                    if (!success) console.error('Print failed:', failureReason);
                    workerWindow.destroy();
                });
            }, 2000); // Jeda 2 detik
        });
    });

    // Buka DevTools untuk debugging (opsional)
    mainWindow.webContents.openDevTools();
    // Memuat URL localhost Anda
    mainWindow.loadFile(path.join(__dirname, './pc/login.html'));
}
 
// Panggil createWindow() saat aplikasi sudah siap.
const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
    // Jika gagal mendapatkan lock, berarti ada instance lain yang sudah berjalan
    app.quit();
} else {
    // Jika user mencoba membuka instance kedua, fokuskan ke window yang sudah ada
    app.on('second-instance', (event, commandLine, workingDirectory) => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.focus();
        }
    });

    app.whenReady().then(() => {
        createWindow();
    });
}


// Keluar saat semua jendela ditutup (kecuali di macOS).
app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});