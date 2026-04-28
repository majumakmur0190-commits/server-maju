const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    saveConfig: (config) => ipcRenderer.invoke('save-config', config),
    loadConfig: () => ipcRenderer.invoke('load-config'),
    getPrinters: () => ipcRenderer.invoke('get-printers'),
    printURL: (url, printerName) => ipcRenderer.send('print-url', url, printerName)
});
