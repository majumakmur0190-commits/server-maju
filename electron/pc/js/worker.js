/**
 * Web Worker untuk sinkronisasi data produk di latar belakang.
 */
self.onmessage = async function (e) {
    const { command, apiUrl } = e.data;

    if (command === 'sync') {
        if (!apiUrl) {
            self.postMessage({ status: 'error', message: 'API URL tidak disediakan.' });
            return;
        }

        try {
            // Lakukan fetch data dari API
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            // Kirim data kembali ke thread utama jika berhasil
            if (result.status === 'success') {
                self.postMessage({ status: 'success', data: result.data });
            } else {
                self.postMessage({ status: 'error', message: result.message || 'Gagal mengambil data dari API.' });
            }
        } catch (error) {
            // Kirim pesan error jika fetch gagal
            self.postMessage({ status: 'error', message: `Gagal melakukan sinkronisasi: ${error.message}` });
        }
    }
};
