<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://staging.api.parcelhub.com.my/v1/shipments/label',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('hawb_no' => 'WSF770432'),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json',
    'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjA5YWQyZWQ2ZDcwMTBiMWI3YTIyOTBjZGZiZDIxMGE0YWVjZjE3MjljYzIxNDc1ZDFjMDIyYzNlZjYwNDE3Mzk3NTcwY2E2ZTY1MGMzYmQ2In0.eyJhdWQiOiIxIiwianRpIjoiMDlhZDJlZDZkNzAxMGIxYjdhMjI5MGNkZmJkMjEwYTRhZWNmMTcyOWNjMjE0NzVkMWMwMjJjM2VmNjA0MTczOTc1NzBjYTZlNjUwYzNiZDYiLCJpYXQiOjE2NzkyNzk0MjcsIm5iZiI6MTY3OTI3OTQyNywiZXhwIjoxNzEwOTAxODI3LCJzdWIiOiIxMDM2Iiwic2NvcGVzIjpbXX0.JQH0ps4MNVln_Xz-cGFZIUb4yT_mblNL4H12H-rL3ID7yutZIaI9hqX-Jy55JHUuRuBnOfg6jDnrKKPmZLIHzoLw5SfGoklyBQOLC5-e9Nw4khWdJhENdHUsbPoGpKMqajkIAMEquv0oaFwLSTBQcxldGeN2pznPClg6NYGwJZRU-rFW7smFUGgaYjtOQBH5-Ni4LoZQvo0c_XCFi0iNCCoigBPuNhIvLdzt8o4IHLmTaRSVNW8_nZolH7td4WHHQz26sKeTeAAlhcGc3TS302AmjCK0M9EfNeLSyh80mnH5r8ppou8rfwK2CCp-eWi05O_eZaXkqfFTTv2lWT7DS8Nabr_W-jVSoVQTjW6gdgJ1OxxGw41qk3OZ35xn_5eNct0cdKfa_tlGoaR-FBOXyRlxpC729CD_9VXtvKMIOj-EEMUcF5lz_Jj4gkvdXi58E9GsyxGEFyFUdJup-_dSEfiRioxEIa3LzzuS7SKSnd0-veUsth0ZJUeotHn_-sMiURkrZHkpedqaPrPuaaEQGiWWJSAazwEQ6kTEblxqY3Qirhx1OvaSGgne-yHI2wV88SxFY6tJCGzqcT_H5sr8gbPz-sdZr6K5aF9eJbZo2mVXp3DtnrqCIoXYqZNwaj8XLjEernKlXtsIF7iM7SHbLJciSdW-MOcJbEWin--LnsI'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;

?>