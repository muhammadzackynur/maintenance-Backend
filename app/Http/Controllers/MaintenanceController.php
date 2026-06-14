<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use App\Models\ReportImage; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http; // Digunakan untuk memanggil API OneSignal
use Illuminate\Support\Facades\Log;  // Digunakan untuk mencatat error jika notifikasi gagal

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            // 1. Validasi data teks dan foto
            $validated = $request->validate([
                'user_id'           => 'required',
                'area'              => 'required|string',
                'district'          => 'required|string',
                'witel'             => 'required|string',
                'sto'               => 'required|string',
                'mitra_pelaksana'   => 'required|string',
                'kategori_kegiatan' => 'required|string',
                'uraian_pekerjaan'  => 'required|string',
                'teknisi'           => 'required|string',
                
                // Koordinat Lokasi
                'latitude'          => 'nullable|string', 
                'longitude'         => 'nullable|string',
                'lokasi_pekerjaan'  => 'nullable|string',

                // Validasi Foto
                'foto_before'       => 'required|array|min:1', 
                'foto_before.*'     => 'image|max:5120',   
                
                'foto_progress'     => 'nullable|array',
                'foto_progress.*'   => 'nullable|image|max:5120',
                
                'foto_after'        => 'nullable|array',
                'foto_after.*'      => 'nullable|image|max:5120',
            ], [
                'foto_before.required' => 'Bukti foto "Before" wajib diunggah!',
                'foto_before.min'      => 'Minimal harus mengunggah 1 foto "Before".',
            ]);

            // 2. Simpan data teks utama ke tabel maintenance_reports
            $report = MaintenanceReport::create($request->except(['foto_before', 'foto_progress', 'foto_after']));

            // 3. Proses looping untuk menyimpan banyak foto ke tabel relasi
            $categories = ['foto_before', 'foto_progress', 'foto_after'];

            foreach ($categories as $category) {
                if ($request->hasFile($category)) {
                    foreach ($request->file($category) as $file) {
                        $path = $file->store('reports', 'public');

                        ReportImage::create([
                            'maintenance_report_id' => $report->id,
                            'image_path'            => $path,
                            'type'                  => str_replace('foto_', '', $category)
                        ]);
                    }
                }
            }

            // 4. KIRIM NOTIFIKASI KE TIM ADMINISTRASI VIA ONESIGNAL
            $this->sendNotification($request->teknisi, $request->sto);

            return response()->json([
                'success' => true,
                'message' => 'Laporan dan bukti foto berhasil dikirim!',
                'data'    => $report->load('images') 
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: Bukti foto belum lengkap.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fungsi Helper untuk mengirim notifikasi OneSignal
     */
    private function sendNotification($teknisi, $sto)
    {
        $appId = "c5e1b4de-5fdf-406e-ab45-7bb5b47ac450";
        $restApiKey = "os_v2_app_yxq3jxs735ag5k2fpo23i6wekckbhlnhbbcehfm23zeljvnikxrm3ytrs5fyvojekd3jsygalvfp62mefspwovvu7coaei2lgc42j2i";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $restApiKey,
                'Content-Type'  => 'application/json',
                'accept'        => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id'   => $appId,
                'filters'  => [
                    ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => 'tim_administrasi']
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

    public function index()
    {
        $reports = MaintenanceReport::with('images')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $reports], 200);
    }

    public function updateData(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);

        $dataToUpdate = $request->except(['_method', 'evidence_material', 'evidence_ukur', 'evidence_pendukung']);
        $files = ['evidence_material', 'evidence_ukur', 'evidence_pendukung'];
        
        foreach ($files as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = 'MAINT-' . $id . '_' . ucfirst(str_replace('evidence_', '', $field)) . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('evidences', $filename, 'public');
                $dataToUpdate[$field] = $path;
            }
        }

        $report->update($dataToUpdate);
        return response()->json(['success' => true, 'data' => $report], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $report->status = $request->status;
        $report->save();
        return response()->json(['message' => 'Status diperbarui', 'data' => $report], 200);
    }

    public function addPhotos(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);

        $categories = ['foto_before', 'foto_progress', 'foto_after'];
        $uploadedCount = 0;
        
        foreach ($categories as $category) {
            if ($request->hasFile($category)) {
                foreach ($request->file($category) as $file) {
                    $path = $file->store('reports', 'public');
                    ReportImage::create([
                        'maintenance_report_id' => $report->id,
                        'image_path'            => $path,
                        'type'                  => str_replace('foto_', '', $category) 
                    ]);
                    $uploadedCount++;
                }
            }
        }

        return $uploadedCount == 0 
            ? response()->json(['success' => false, 'message' => 'Tidak ada foto'], 400)
            : response()->json(['success' => true, 'message' => "$uploadedCount foto berhasil ditambahkan!", 'data' => $report->load('images')], 200);
    }

    public function downloadPhotosZip($id)
    {
        try {
            // Ambil data laporan beserta relasi fotonya
            $report = \App\Models\MaintenanceReport::with('images')->findOrFail($id);
            
            if ($report->images->isEmpty()) {
                return response()->json(['message' => 'Tidak ada foto untuk diunduh'], 404);
            }

            // Inisialisasi proses pembuatan ZIP
            $zip = new \ZipArchive();
            $zipFileName = 'Bukti_Foto_MAINT-' . str_pad($id, 3, '0', STR_PAD_LEFT) . '.zip';
            $zipPath = storage_path('app/public/' . $zipFileName);

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                
                // Buat struktur Folder Utama di dalam ZIP
                $baseDir = 'Bukti_MAINT_' . str_pad($id, 3, '0', STR_PAD_LEFT) . '/';
                
                // Buat Sub-Folder di dalamnya
                $zip->addEmptyDir($baseDir . 'Before');
                $zip->addEmptyDir($baseDir . 'Progress');
                $zip->addEmptyDir($baseDir . 'After');

                // Masukkan setiap foto ke dalam foldernya masing-masing
                foreach ($report->images as $index => $img) {
                    $filePath = storage_path('app/public/' . $img->image_path);
                    
                    if (file_exists($filePath)) {
                        $type = ucfirst(strtolower($img->type)); // Hasilnya: Before / Progress / After
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        
                        // Path file di dalam ZIP (Contoh: Bukti_MAINT_001/Before/Before_1.jpg)
                        $fileNameInZip = $baseDir . $type . '/' . $type . '_' . ($index + 1) . '.' . $ext;
                        
                        $zip->addFile($filePath, $fileNameInZip);
                    }
                }
                $zip->close();
            }

            // Kembalikan file ZIP untuk diunduh, lalu otomatis HAPUS file zip dari server agar memori tidak penuh
            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat file ZIP: ' . $e->getMessage()], 500);
        }
    }

    public function exportWord($id)
    {
        try {
            $report = \App\Models\MaintenanceReport::with('images')->findOrFail($id);

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

            // ── JUDUL ──────────────────────────────────────────────
            $section->addText(
                "EVIDENCE DOKUMENTASI PEKERJAAN",
                ['name' => 'Arial', 'size' => 14, 'bold' => true],
                $centerPara
            );

            // ── GARIS 1: di bawah judul ───────────────────────────
            $section->addText('', $normalStyle, $lineStyle);

            // ── INFO ROWS ─────────────────────────────────────────
            $addInfoRow = function($key, $value, $withBorderBottom = false) use ($section, $boldStyle, $normalStyle, $paraStyle) {
                $rowPara = array_merge($paraStyle, [
                    'tabs' => [
                        new \PhpOffice\PhpWord\Style\Tab('left', 3500),
                        new \PhpOffice\PhpWord\Style\Tab('left', 3800),
                    ],
                ]);
                
                if ($withBorderBottom) {
                    $rowPara = array_merge($rowPara, [
                        'borderBottomSize'  => 12,
                        'borderBottomColor' => '000000',
                    ]);
                }
                
                $textRun = $section->addTextRun($rowPara);
                $textRun->addText($key,    $boldStyle);
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

            // ── FOTO ───────────────────────────────────────────────
            $colWidth  = 2835;
            $imgWidth  = 140;
            $imgHeight = 157;

            $insertImages = function($title, $type) use ($section, $report, $boldStyle, $normalStyle, $paraStyle, $centerPara, $colWidth, $imgWidth, $imgHeight) {

                // ── JUDUL DI LUAR TABEL, CENTER ───────────────────
                $section->addText($title, $boldStyle, $centerPara);

                $images = $report->images->where('type', $type)->values();

                $cellBorder = [
                    'borderTopSize'     => 6,
                    'borderBottomSize'  => 6,
                    'borderLeftSize'    => 6,
                    'borderRightSize'   => 6,
                    'borderTopColor'    => '000000',
                    'borderBottomColor' => '000000',
                    'borderLeftColor'   => '000000',
                    'borderRightColor'  => '000000',
                    'valign'            => 'center',
                ];

                $tbl = $section->addTable([
                    'borderSize'  => 6,
                    'borderColor' => '000000',
                    'cellMargin'  => 0,
                    'alignment'   => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                // ── BARIS 1: header kecil kosong 3 kolom ──────────
                $tbl->addRow(200);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);
                $tbl->addCell($colWidth, $cellBorder)->addText('', $normalStyle, $paraStyle);

                // ── BARIS 2+: foto 3 kolom ────────────────────────
                $totalImages = count($images);
                $totalCells  = max(3, $totalImages);
                if ($totalCells % 3 !== 0) {
                    $totalCells = $totalCells + (3 - ($totalCells % 3));
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
                                $cell->addImage($path, [
                                    'width'     => $imgWidth,
                                    'height'    => $imgHeight,
                                    'ratio'     => false,
                                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                                ]);
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

            // ── MATERIAL ───────────────────────────────────────────
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

            // ── KML / KOORDINAT ────────────────────────────────────
            $section->addText("KML", $boldStyle, $paraStyle);
            $section->addText(($report->latitude ?? "-") . ", " . ($report->longitude ?? "-"), $normalStyle, $paraStyle);
            $section->addTextBreak(2, $normalStyle, $paraStyle);

            // ── TANDA TANGAN ───────────────────────────────────────
            $phpWord->addTableStyle('TTDTable', [
                'cellMarginTop'    => 0,
                'cellMarginBottom' => 0,
                'cellMarginLeft'   => 100,
                'cellMarginRight'  => 100,
            ]);
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

            // ── SIMPAN & DOWNLOAD ──────────────────────────────────
            $fileName = 'Laporan_Pekerjaan_MAINT-' . str_pad($id, 3, '0', STR_PAD_LEFT) . '.docx';
            $filePath = storage_path('app/public/' . $fileName);

            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($filePath);

            return response()->download($filePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat file Word: ' . $e->getMessage()], 500);
        }
    }
}