<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$offset = 0;
$data = array();
$multiCurl = curl_multi_init();
$curlHandles = [];
$maxRequests = 5; // Jumlah permintaan simultan

// Fungsi untuk membuat handle curl baru
function createCurlHandle($offset) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api-sscasn.bkn.go.id/2024/portal/spf?kode_ref_pend=5101087&offset=' . $offset);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        'Connection: keep-alive',
        'DNT: 1',
        'Origin: https://sscasn.bkn.go.id',
        'Referer: https://sscasn.bkn.go.id/',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
        'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
        'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "Android"',
    ]);
    return $ch;
}

// Tambahkan handle curl ke multiCurl
for ($i = 0; $i < $maxRequests; $i++) {
    $ch = createCurlHandle($offset);
    curl_multi_add_handle($multiCurl, $ch);
    $curlHandles[$offset] = $ch;
    $offset += 10;
}

do {
    curl_multi_exec($multiCurl, $running);

    while ($info = curl_multi_info_read($multiCurl)) {
        $ch = $info['handle'];
        $response = curl_multi_getcontent($ch);
        $decodedResponse = json_decode($response, true);

        // Tambahkan data yang diambil ke dalam array data
        if ($decodedResponse !== null && isset($decodedResponse["data"]["data"])) {
            foreach ($decodedResponse["data"]["data"] as $item) {
                // Hapus formasi_id
                unset($item['formasi_id']);

                // Ubah format gaji_min dan gaji_max menjadi float dan format uang IDR
                $item['gaji_min'] = (float) $item['gaji_min'];
                $item['gaji_max'] = (float) $item['gaji_max'];
                $data[] = $item;
            }
            echo "Collected until " . array_search($ch, $curlHandles) . " data \n";
        }
        
        // Jika jumlah data kurang dari 10, hentikan proses
        if (isset($decodedResponse["data"]["data"]) && count($decodedResponse["data"]["data"]) < 10) {
            break 2; // Keluar dari loop utama dan do-while
        }
        
        // Tutup handle curl yang sudah selesai
        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);

        // Buat request baru jika offset masih dalam rentang
        if ($offset <= 9450) {
            $ch = createCurlHandle($offset);
            curl_multi_add_handle($multiCurl, $ch);
            $curlHandles[$offset] = $ch;
            $offset += 10;
        }
    }
} while ($running);

curl_multi_close($multiCurl);
echo "generate excel file before exit";

// Ekspor data ke file Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Teknik Informatika 2024');

$headers = array_keys($data[0]);
$sheet->fromArray($headers, NULL, 'A1');
$sheet->fromArray($data, NULL, 'A2');

$writer = new Xlsx($spreadsheet);
$writer->save('Data-CPNS-Teknik-Informatika-2024.xlsx');

echo "Data berhasil diekspor ke Data-CPNS-Teknik-Informatika-2024.xlsx\n";
