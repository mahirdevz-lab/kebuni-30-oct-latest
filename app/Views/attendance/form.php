<?= $this->extend('layouts/blank') ?>

<?= $this->section('content') ?>
<style>
    /* Un-mirror kamera depan: preview tampil normal (tidak seperti kaca) */
    #cameraPreview {
        transform: scaleX(-1);
        -webkit-transform: scaleX(-1);
        width: 100%;
        border-radius: 0.5rem;
        background: #000;
    }
    #photoPreview {
        width: 100%;
        border-radius: 0.5rem;
    }
</style>
<div class="attendance-wrapper">
    <div class="text-center mb-3">
        <i class="fa-solid fa-camera text-primary" style="font-size:2rem;"></i>
        <h4 class="mt-2 mb-0">Absensi Karyawan</h4>
        <p class="text-muted small mb-0" id="clock">--:--:--</p>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="alertBox" class="alert d-none py-2 small"></div>

            <div class="mb-3">
                <label class="form-label">Nama <span class="text-danger">*</span></label>
                <input type="text" id="nama" class="form-control" maxlength="150" autocomplete="name" placeholder="Nama lengkap">
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Jenis Absensi</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="attendance_type" id="typeIn" value="CHECK_IN" checked>
                    <label class="btn btn-outline-primary" for="typeIn">Masuk</label>
                    <input type="radio" class="btn-check" name="attendance_type" id="typeOut" value="CHECK_OUT">
                    <label class="btn btn-outline-primary" for="typeOut">Pulang</label>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Foto Selfie <span class="text-danger">*</span></label>
                <video id="cameraPreview" autoplay playsinline muted></video>
                <img id="photoPreview" class="d-none" alt="Preview foto">
                <canvas id="captureCanvas" class="d-none"></canvas>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" id="btnCapture"><i class="fa-solid fa-camera me-1"></i> Ambil Foto</button>
                    <button type="button" class="btn btn-outline-secondary flex-fill d-none" id="btnRetake"><i class="fa-solid fa-rotate me-1"></i> Ulangi</button>
                </div>
            </div>

            <div class="small mb-3" id="geoStatus"><i class="fa-solid fa-location-dot me-1"></i> Mendeteksi lokasi...</div>

            <button type="button" class="btn btn-primary w-100" id="btnSubmit"><i class="fa-solid fa-paper-plane me-1"></i> Kirim Absensi</button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const state = { photo: null, lat: null, lng: null, accuracy: null, stream: null };

function showAlert(msg, type) {
    $('#alertBox').removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).text(msg);
}

setInterval(() => $('#clock').text(new Date().toLocaleTimeString('id-ID')), 1000);

async function startCamera() {
    try {
        state.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        document.getElementById('cameraPreview').srcObject = state.stream;
    } catch (e) {
        showAlert('Kamera tidak dapat diakses. Izinkan akses kamera (butuh HTTPS atau localhost).', 'danger');
    }
}

function detectLocation() {
    if (! navigator.geolocation) { $('#geoStatus').text('Browser tidak mendukung lokasi.'); return; }
    navigator.geolocation.getCurrentPosition(pos => {
        state.lat = pos.coords.latitude; state.lng = pos.coords.longitude; state.accuracy = pos.coords.accuracy;
        $('#geoStatus').html('<i class="fa-solid fa-location-dot text-success me-1"></i> Lokasi terdeteksi (akurasi ±' + Math.round(pos.coords.accuracy) + ' m)');
    }, () => {
        $('#geoStatus').html('<i class="fa-solid fa-location-dot text-warning me-1"></i> Lokasi tidak tersedia — absensi tetap dikirim dengan status lokasi tidak diketahui.');
    }, { enableHighAccuracy: true, timeout: 10000 });
}

$('#btnCapture').on('click', function () {
    const video = document.getElementById('cameraPreview');
    if (! video.videoWidth) { showAlert('Kamera belum siap.', 'warning'); return; }
    const scale = Math.min(1, 640 / video.videoWidth);
    const canvas = document.getElementById('captureCanvas');
    canvas.width = video.videoWidth * scale;
    canvas.height = video.videoHeight * scale;
    // Flip horizontal saat capture supaya hasil foto sama dengan preview (tidak mirror)
    const ctx = canvas.getContext('2d');
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    state.photo = canvas.toDataURL('image/jpeg', 0.7);
    $('#photoPreview').attr('src', state.photo).removeClass('d-none');
    $('#cameraPreview').addClass('d-none');
    $('#btnCapture').addClass('d-none');
    $('#btnRetake').removeClass('d-none');
});

$('#btnRetake').on('click', function () {
    state.photo = null;
    $('#photoPreview').addClass('d-none');
    $('#cameraPreview').removeClass('d-none');
    $('#btnCapture').removeClass('d-none');
    $('#btnRetake').addClass('d-none');
});

$('#btnSubmit').on('click', function () {
    const nama = $('#nama').val().trim();
    if (nama.length < 3) { showAlert('Nama wajib diisi (minimal 3 karakter).', 'warning'); return; }
    if (! state.photo) { showAlert('Silakan ambil foto selfie terlebih dahulu.', 'warning'); return; }

    const $btn = $(this).prop('disabled', true);
    $.post("<?= base_url('absensi/submit') ?>", {
        nama: nama,
        attendance_type: $('input[name=attendance_type]:checked').val(),
        photo: state.photo,
        latitude: state.lat,
        longitude: state.lng,
        accuracy: state.accuracy,
        device_info: navigator.platform || ''
    }).done(res => {
        showAlert(res.message, 'success');
        $('#btnRetake').trigger('click');
    }).fail(xhr => {
        showAlert((xhr.responseJSON && xhr.responseJSON.message) || 'Terjadi kesalahan pada server.', 'danger');
    }).always(() => $btn.prop('disabled', false));
});

startCamera();
detectLocation();
</script>
<?= $this->endSection() ?>
