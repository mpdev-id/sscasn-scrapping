<?php
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
            $data = array_merge($data, $decodedResponse["data"]["data"]);
            echo "Berhasil get data offset " . array_search($ch, $curlHandles) . "\n";
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

// Simpan data dalam file JSON
$fp = fopen('data.json', 'w');
fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
fclose($fp);

curl_multi_close($multiCurl);
