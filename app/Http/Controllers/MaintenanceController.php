<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use App\Models\ReportImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    // ====================================================================
    // KUNCI PRIVATE SERVER UNTUK DEKRIPSI ECC (X25519 + AES-GCM)
    // Harus sama dengan pasangan dari public key yang ada di Flutter
    // Simpan nilai ini di .env agar lebih aman:
    //   ECC_PRIVATE_KEY=<base64_private_key_anda>
    // ====================================================================
    private string $serverPrivateKeyBase64 = 'jC28mahRvw+YwdmBX3r8SU9DXyLmzb9NWc7rFnqAk+4=';

    // ====================================================================
    // HELPER: DEKRIPSI SATU FIELD YANG DIENKRIPSI FLUTTER (ECC + AES-GCM)
    // ====================================================================

    /**
     * Mendekripsi satu nilai yang sudah dienkripsi oleh EccHelper Flutter.
     * Format payload (setelah base64 decode + json decode):
     *   { client_pub_key, nonce, mac, ciphertext }
     */
    private function decryptField(?string $encryptedValue): string
{
    if (empty($encryptedValue)) {
        return '';
    }

    $start = microtime(true);

    try {

        $json = base64_decode($encryptedValue);
        $payload = json_decode($json, true);

        if (
            !$payload ||
            !isset(
                $payload['client_pub_key'],
                $payload['nonce'],
                $payload['mac'],
                $payload['ciphertext']
            )
        ) {
            return $encryptedValue;
        }

        $clientPubKeyBytes = base64_decode($payload['client_pub_key']);
        $nonce             = base64_decode($payload['nonce']);
        $mac               = base64_decode($payload['mac']);
        $ciphertext        = base64_decode($payload['ciphertext']);

        $serverPrivKeyBytes = base64_decode($this->serverPrivateKeyBase64);

        $sharedSecret = sodium_crypto_scalarmult(
            $serverPrivKeyBytes,
            $clientPubKeyBytes
        );

        if (!sodium_crypto_aead_aes256gcm_is_available()) {

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $sharedSecret,
                OPENSSL_RAW_DATA,
                $nonce,
                $mac
            );

        } else {

            $ciphertextWithTag = $ciphertext . $mac;

            $decrypted = sodium_crypto_aead_aes256gcm_decrypt(
                $ciphertextWithTag,
                '',
                $nonce,
                $sharedSecret
            );
        }

        // Hitung waktu dekripsi
        $elapsedMs = round((microtime(true) - $start) * 1000, 3);

        Log::info("ECC DECRYPT SUCCESS | Waktu: {$elapsedMs} ms");

        return $decrypted ?: $encryptedValue;

    } catch (\Throwable $e) {

        $elapsedMs = round((microtime(true) - $start) * 1000, 3);

        Log::error(
            "ECC DECRYPT ERROR | Waktu: {$elapsedMs} ms | {$e->getMessage()}"
        );

        return $encryptedValue;
    }
}

    // ====================================================================
    // HELPER: TRANSFORM SATU REPORT — DEKRIPSI SEMUA FIELD SENSITIF
    // Dipanggil di index(), getHistory(), dan endpoint lain yang baca data
    // ====================================================================

    private function transformDecryptedReport(MaintenanceReport $report): MaintenanceReport
    {
        $fieldsToDecrypt = [
            'area',
            'district',
            'witel',
            'sto',
            'mitra_pelaksana',
            'kategori_kegiatan',
            'uraian_pekerjaan',
            'teknisi',
            'latitude',
            'longitude',
            'lokasi_pekerjaan',
        ];

        foreach ($fieldsToDecrypt as $field) {
            if (!empty($report->$field)) {
                $report->$field = $this->decryptField($report->$field);
            }
        }

        return $report;
    }

    // ====================================================================
    // STORE: SIMPAN LAPORAN BARU (DATA MASUK SUDAH TERENKRIPSI DARI FLUTTER)
    // ====================================================================

    public function store(Request $request)
    {
        try {
            $request->validate([
                'user_id'           => 'required',
                'area'              => 'required',
                'district'          => 'required',
                'witel'             => 'required',
                'sto'               => 'required',
                'mitra_pelaksana'   => 'required',
                'kategori_kegiatan' => 'required',
                'uraian_pekerjaan'  => 'required',
                'teknisi'           => 'required',
                'latitude'          => 'nullable',
                'longitude'         => 'nullable',
                'lokasi_pekerjaan'  => 'nullable',
                'foto_before'       => 'required|array|min:1',
                'foto_before.*'     => 'image|max:5120',
            ]);

            // Simpan langsung — data sudah terenkripsi dari Flutter
            $report = MaintenanceReport::create(
                $request->except(['foto_before', 'foto_progress', 'foto_after'])
            );

            // Simpan foto
            foreach (['foto_before', 'foto_progress', 'foto_after'] as $category) {
                if ($request->hasFile($category)) {
                    foreach ($request->file($category) as $file) {
                        $path = $file->store('reports', 'public');
                        ReportImage::create([
                            'maintenance_report_id' => $report->id,
                            'image_path'            => $path,
                            'type'                  => str_replace('foto_', '', $category),
                        ]);
                    }
                }
            }

            // Notifikasi pakai teks umum karena teknisi sudah terenkripsi
            $this->sendNotification("Seorang Teknisi", "salah satu STO");

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dikirim!',
                'data'    => $report->load('images'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ====================================================================
    // INDEX: AMBIL SEMUA LAPORAN — DATA DIDEKRIPSI SEBELUM DIKIRIM KE FLUTTER
    // INILAH YANG DIPERBAIKI: transformDecryptedReport() sekarang aktif dipanggil
    // ====================================================================

    public function index()
    {
        $reports = MaintenanceReport::with('images')->orderBy('id', 'desc')->get();

        // PERBAIKAN UTAMA: Dekripsi setiap field sebelum dikirim ke Flutter
        $reports->transform(function ($report) {
            return $this->transformDecryptedReport($report);
        });

        return response()->json(['status' => 'success', 'data' => $reports], 200);
    }

    // ====================================================================
    // GET HISTORY: RIWAYAT BERDASARKAN USER (PELAPOR / TEKNISI YANG DIUTUS)
    // ====================================================================

    public function getHistory($userId)
    {
        $reports = MaintenanceReport::with('images')
            ->whereIn('status', ['PENDING', 'OPEN', 'CLOSE'])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereJsonContains('assigned_technicians', (int) $userId)
                      ->orWhereJsonContains('assigned_technicians', (string) $userId);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($reports->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada riwayat pekerjaan.',
                'data'    => [],
            ], 200);
        }

        $reports->transform(function ($report) {
            return $this->transformDecryptedReport($report);
        });

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data riwayat.',
            'data'    => $reports,
        ], 200);
    }

    // ====================================================================
    // ASSIGN TEKNISI MANUAL (OLEH ADMIN)
    // ====================================================================

    public function assignTechnicians(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data laporan tidak ditemukan'], 404);
        }

        $request->validate(['assigned_technicians' => 'required|array']);

        $report->assigned_technicians = $request->assigned_technicians;
        $report->status = 'OPEN';
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Teknisi berhasil ditugaskan dan tiket sekarang OPEN.',
            'data'    => $report,
        ], 200);
    }

    // ====================================================================
    // UPDATE DATA LAPORAN
    // ====================================================================

    public function updateData(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $dataToUpdate = $request->except(['_method', 'evidence_material', 'evidence_ukur', 'evidence_pendukung']);

        foreach (['evidence_material', 'evidence_ukur', 'evidence_pendukung'] as $field) {
            if ($request->hasFile($field)) {
                $file     = $request->file($field);
                $filename = 'MAINT-' . $id . '_' . ucfirst(str_replace('evidence_', '', $field)) . '_' . $file->getClientOriginalName();
                $path     = $file->storeAs('evidences', $filename, 'public');
                $dataToUpdate[$field] = $path;
            }
        }

        $report->update($dataToUpdate);
        return response()->json(['success' => true, 'data' => $report], 200);
    }

    // ====================================================================
    // UPDATE STATUS (TERMASUK AUTO-ASSIGN TEKNISI SAAT STATUS OPEN)
    // ====================================================================

    public function updateStatus(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $newStatus    = $request->status;
        $report->status = $newStatus;

        if ($newStatus === 'OPEN') {
            $technicians = \App\Models\User::where('role', 'Pengguna Lapangan')
                // ->where('sto', $report->sto) // Aktifkan untuk filter per STO
                ->inRandomOrder()
                ->take(5)
                ->pluck('id')
                ->toArray();

            $report->assigned_technicians = $technicians;
        }

        $report->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Status diperbarui. Jika OPEN, teknisi telah diutus otomatis oleh sistem.',
            'data'     => $report,
            'assigned' => $report->assigned_technicians ?? [],
        ], 200);
    }

    // ====================================================================
    // TAMBAH FOTO SUSULAN
    // ====================================================================

    public function addPhotos(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $uploadedCount = 0;

        foreach (['foto_before', 'foto_progress', 'foto_after'] as $category) {
            if ($request->hasFile($category)) {
                foreach ($request->file($category) as $file) {
                    $path = $file->store('reports', 'public');
                    ReportImage::create([
                        'maintenance_report_id' => $report->id,
                        'image_path'            => $path,
                        'type'                  => str_replace('foto_', '', $category),
                    ]);
                    $uploadedCount++;
                }
            }
        }

        return $uploadedCount === 0
            ? response()->json(['success' => false, 'message' => 'Tidak ada foto'], 400)
            : response()->json(['success' => true, 'message' => "$uploadedCount foto berhasil ditambahkan!", 'data' => $report->load('images')], 200);
    }

    // ====================================================================
    // DOWNLOAD FOTO ZIP
    // ====================================================================

    public function downloadPhotosZip($id)
    {
        try {
            $report = MaintenanceReport::with('images')->findOrFail($id);

            if ($report->images->isEmpty()) {
                return response()->json(['message' => 'Tidak ada foto untuk diunduh'], 404);
            }

            $zip         = new \ZipArchive();
            $zipFileName = 'Bukti_Foto_MAINT-' . str_pad($id, 3, '0', STR_PAD_LEFT) . '.zip';
            $zipPath     = storage_path('app/public/' . $zipFileName);

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $baseDir = 'Bukti_MAINT_' . str_pad($id, 3, '0', STR_PAD_LEFT) . '/';
                $zip->addEmptyDir($baseDir . 'Before');
                $zip->addEmptyDir($baseDir . 'Progress');
                $zip->addEmptyDir($baseDir . 'After');

                foreach ($report->images as $index => $img) {
                    $filePath = storage_path('app/public/' . $img->image_path);
                    if (file_exists($filePath)) {
                        $type          = ucfirst(strtolower($img->type));
                        $ext           = pathinfo($filePath, PATHINFO_EXTENSION);
                        $fileNameInZip = $baseDir . $type . '/' . $type . '_' . ($index + 1) . '.' . $ext;
                        $zip->addFile($filePath, $fileNameInZip);
                    }
                }
                $zip->close();
            }

            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat file ZIP: ' . $e->getMessage()], 500);
        }
    }

    // ====================================================================
    // EXPORT WORD
    // ====================================================================

    public function exportWord($id)
    {
        try {
            // Ambil data mentah dulu, lalu dekripsi untuk keperluan dokumen Word
            $report = MaintenanceReport::with('images')->findOrFail($id);
            $report = $this->transformDecryptedReport($report);

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName('Arial');
            $phpWord->setDefaultFontSize(11);
            $phpWord->setDefaultParagraphStyle([
                'spacing'     => 0,
                'spaceBefore' => 0,
                'spaceAfter'  => 0,
                'line'        => 240,
            ]);

            $sectionStyle = [
                'pageSizeW'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21),
                'pageSizeH'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.7),
                'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
                'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
                'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.0),
                'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.0),
            ];
            $section = $phpWord->addSection($sectionStyle);

            $boldStyle   = ['name' => 'Arial', 'size' => 11, 'bold' => true];
            $normalStyle = ['name' => 'Arial', 'size' => 11];
            $paraStyle   = ['spaceBefore' => 0, 'spaceAfter' => 0, 'spacing' => 0];
            $centerPara  = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 0, 'spacing' => 0];
            $lineStyle   = ['spaceBefore' => 0, 'spaceAfter' => 0, 'spacing' => 0, 'borderBottomSize' => 12, 'borderBottomColor' => '000000'];

            $logoPath = public_path('images/logo-telkom.png');
            if (file_exists($logoPath)) {
                $section->addImage($logoPath, [
                    'width'     => 120,
                    'height'    => 55,
                    'ratio'     => true,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
                ]);
            }

            $section->addText("EVIDENCE DOKUMENTASI PEKERJAAN", ['name' => 'Arial', 'size' => 14, 'bold' => true], $centerPara);
            $section->addText('', $normalStyle, $lineStyle);

            $addInfoRow = function ($key, $value, $withBorderBottom = false) use ($section, $boldStyle, $normalStyle, $paraStyle) {
                $rowPara = array_merge($paraStyle, [
                    'tabs' => [
                        new \PhpOffice\PhpWord\Style\Tab('left', 3500),
                        new \PhpOffice\PhpWord\Style\Tab('left', 3800),
                    ],
                ]);
                if ($withBorderBottom) {
                    $rowPara = array_merge($rowPara, ['borderBottomSize' => 12, 'borderBottomColor' => '000000']);
                }
                $textRun = $section->addTextRun($rowPara);
                $textRun->addText($key, $boldStyle);
                $textRun->addText("\t:\t", $boldStyle);
                $textRun->addText(strtoupper($value ?? ''), $boldStyle);
            };

            $addInfoRow("PROYEK",        "Pekerjaan CAPEX QE RECOVERY Telkom Regional 3 Batch-1");
            $addInfoRow("KONTRAK",       "K.TEL.002415/HK.810/T3R-0A000000");
            $addInfoRow("SURAT PESANAN", "-");
            $addInfoRow("WITEL",         $report->witel ?? "-");
            $addInfoRow("LOKASI",        $report->uraian_pekerjaan ?? "-");
            $addInfoRow("PELAKSANA",     "TELKOM AKSES", true);

            $section->addTextBreak(1, $normalStyle, $paraStyle);

            $colWidth  = 2835;
            $imgWidth  = 140;
            $imgHeight = 157;

            $insertImages = function ($title, $type) use ($section, $report, $boldStyle, $normalStyle, $paraStyle, $centerPara, $colWidth, $imgWidth, $imgHeight) {
                $section->addText($title, $boldStyle, $centerPara);
                $images     = $report->images->where('type', $type)->values();
                $cellBorder = [
                    'borderTopSize' => 6, 'borderBottomSize' => 6, 'borderLeftSize' => 6, 'borderRightSize' => 6,
                    'borderTopColor' => '000000', 'borderBottomColor' => '000000', 'borderLeftColor' => '000000', 'borderRightColor' => '000000',
                    'valign' => 'center',
                ];
                $tbl = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
                $tbl->addRow(200);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);

                $totalImages = count($images);
                $totalCells  = max(3, $totalImages);
                if ($totalCells % 3 !== 0) {
                    $totalCells += 3 - ($totalCells % 3);
                }

                $imgIndex = 0;
                for ($row = 0; $row < ($totalCells / 3); $row++) {
                    $tbl->addRow(3200);
                    for ($col = 0; $col < 3; $col++) {
                        $cell = $tbl->addCell($colWidth, array_merge($cellBorder, ['valign' => 'center']));
                        if ($imgIndex < $totalImages) {
                            $img  = $images[$imgIndex];
                            $path = storage_path('app/public/' . $img->image_path);
                            if (file_exists($path)) {
                                $cell->addImage($path, ['width' => $imgWidth, 'height' => $imgHeight, 'ratio' => false, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            } else {
                                $cell->addText("[Tidak Ditemukan]", $normalStyle, $centerPara);
                            }
                        } else {
                            $cell->addText('', $normalStyle, $paraStyle);
                        }
                        $imgIndex++;
                    }
                }
                $section->addTextBreak(1, $normalStyle, $paraStyle);
            };

            $insertImages("SEBELUM",  'before');
            $insertImages("PROGRES",  'progress');
            $insertImages("SESUDAH",  'after');

            $section->addText("MATERIAL", $boldStyle, $paraStyle);
            if (!empty($report->evidence_material)) {
                $path = storage_path('app/public/' . $report->evidence_material);
                if (file_exists($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])) {
                    $section->addImage($path, ['width' => 220, 'height' => 220, 'ratio' => true]);
                } else {
                    $section->addText("Terdapat lampiran file: " . basename($path ?? ''), $normalStyle, $paraStyle);
                }
            } else {
                $section->addText("Tidak ada evidence material.", ['name' => 'Arial', 'size' => 10, 'italic' => true], $paraStyle);
            }
            $section->addTextBreak(1, $normalStyle, $paraStyle);

            $section->addText("KML", $boldStyle, $paraStyle);
            $section->addText(($report->latitude ?? "-") . ", " . ($report->longitude ?? "-"), $normalStyle, $paraStyle);
            $section->addTextBreak(2, $normalStyle, $paraStyle);

            $phpWord->addTableStyle('TTDTable', ['cellMarginTop' => 0, 'cellMarginBottom' => 0, 'cellMarginLeft' => 100, 'cellMarginRight' => 100]);
            $tableTTD = $section->addTable('TTDTable');
            $tableTTD->addRow();

            $cell1 = $tableTTD->addCell(5000);
            $cell1->addText("PT TELKOM INFRASTRUKTUR INDONESIA", $boldStyle, $centerPara);
            $cell1->addText("WASPANG", $boldStyle, $centerPara);
            $cell1->addTextBreak(3);
            $cell1->addText("AHMAD FAUZI", ['bold' => true, 'underline' => 'single', 'name' => 'Arial', 'size' => 11], $centerPara);
            $cell1->addText("NIK. 950132", $normalStyle, $centerPara);

            $cell2 = $tableTTD->addCell(5000);
            $cell2->addText("PT TELKOM AKSES", $boldStyle, $centerPara);
            $cell2->addText("PELAKSANA HARIAN", $boldStyle, $centerPara);
            $cell2->addTextBreak(3);
            $cell2->addText("PRASETYAWAN SULUH PAMBUDI", ['bold' => true, 'underline' => 'single', 'name' => 'Arial', 'size' => 11], $centerPara);
            $cell2->addText("NIK. 865802", $normalStyle, $centerPara);

            $fileName = 'Laporan_Pekerjaan_MAINT-' . str_pad($id, 3, '0', STR_PAD_LEFT) . '.docx';
            $filePath = storage_path('app/public/' . $fileName);

            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($filePath);

            return response()->download($filePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat file Word: ' . $e->getMessage()], 500);
        }
    }

    // ====================================================================
    // HELPER NOTIFIKASI ONESIGNAL
    // ====================================================================

    private function sendNotification(string $teknisi, string $sto): void
    {
        $appId      = "c5e1b4de-5fdf-406e-ab45-7bb5b47ac450";
        $restApiKey = "os_v2_app_yxq3jxs735ag5k2fpo23i6wekckbhlnhbbcehfm23zeljvnikxrm3ytrs5fyvojekd3jsygalvfp62mefspwovvu7coaei2lgc42j2i";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $restApiKey,
                'Content-Type'  => 'application/json',
                'accept'        => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id'   => $appId,
                'filters'  => [
                    ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => 'tim_administrasi'],
                ],
                'headings' => ['en' => 'Laporan Maintenance Baru!'],
                'contents' => ['en' => "Teknisi $teknisi baru saja mengirim laporan pemeliharaan di STO $sto."],
            ]);

            if (!$response->successful()) {
                Log::error('OneSignal Notification Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('OneSignal Exception: ' . $e->getMessage());
        }
    }
}