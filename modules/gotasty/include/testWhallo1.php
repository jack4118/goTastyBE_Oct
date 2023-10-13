<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://staging.api.parcelhub.com.my/v1/shipments/create',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('shipper_name' => 'GoTasty',
  'shipper_address_line_1' => '31-G,Jalan Damai Raya 6',
  'shipper_address_line_2' => 'Alam Damai',
  'shipper_city' => 'Kuala Lumpur',
  'shipper_postcode' => '56000',
  'shipper_state' => 'Kuala Lumpur',
  'shipper_country_code' => 'MY',
  'shipper_tel' => '60182626000',
  'receiver_contact_person' => 'Steven',
  'receiver_address_line_1' => '20,Jalan Perak',
  'receiver_address_line_2' => 'Kuala Lumpur',
  'receiver_city' => 'Kuala Lumpur','receiver_postcode' => '50450','receiver_state' => 'Kuala Lumpur','receiver_country_code' => 'MY','receiver_tel' => '60122590231','description' => 'Food','package_type' => 'parcel','pickup_type' => '1','parcels' => '[{
  "description": "Food",
  "category": "Food",
  "weight": "10",
  "height": "30",
  "width": "30",
  "length": "30",
  "declared_value": "100",
  "currency_code": "MYR"
}]','receiver_name' => 'steven'),
  CURLOPT_HTTPHEADER => array(
    'Accept: application/json',
    'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjA5YWQyZWQ2ZDcwMTBiMWI3YTIyOTBjZGZiZDIxMGE0YWVjZjE3MjljYzIxNDc1ZDFjMDIyYzNlZjYwNDE3Mzk3NTcwY2E2ZTY1MGMzYmQ2In0.eyJhdWQiOiIxIiwianRpIjoiMDlhZDJlZDZkNzAxMGIxYjdhMjI5MGNkZmJkMjEwYTRhZWNmMTcyOWNjMjE0NzVkMWMwMjJjM2VmNjA0MTczOTc1NzBjYTZlNjUwYzNiZDYiLCJpYXQiOjE2NzkyNzk0MjcsIm5iZiI6MTY3OTI3OTQyNywiZXhwIjoxNzEwOTAxODI3LCJzdWIiOiIxMDM2Iiwic2NvcGVzIjpbXX0.JQH0ps4MNVln_Xz-cGFZIUb4yT_mblNL4H12H-rL3ID7yutZIaI9hqX-Jy55JHUuRuBnOfg6jDnrKKPmZLIHzoLw5SfGoklyBQOLC5-e9Nw4khWdJhENdHUsbPoGpKMqajkIAMEquv0oaFwLSTBQcxldGeN2pznPClg6NYGwJZRU-rFW7smFUGgaYjtOQBH5-Ni4LoZQvo0c_XCFi0iNCCoigBPuNhIvLdzt8o4IHLmTaRSVNW8_nZolH7td4WHHQz26sKeTeAAlhcGc3TS302AmjCK0M9EfNeLSyh80mnH5r8ppou8rfwK2CCp-eWi05O_eZaXkqfFTTv2lWT7DS8Nabr_W-jVSoVQTjW6gdgJ1OxxGw41qk3OZ35xn_5eNct0cdKfa_tlGoaR-FBOXyRlxpC729CD_9VXtvKMIOj-EEMUcF5lz_Jj4gkvdXi58E9GsyxGEFyFUdJup-_dSEfiRioxEIa3LzzuS7SKSnd0-veUsth0ZJUeotHn_-sMiURkrZHkpedqaPrPuaaEQGiWWJSAazwEQ6kTEblxqY3Qirhx1OvaSGgne-yHI2wV88SxFY6tJCGzqcT_H5sr8gbPz-sdZr6K5aF9eJbZo2mVXp3DtnrqCIoXYqZNwaj8XLjEernKlXtsIF7iM7SHbLJciSdW-MOcJbEWin--LnsI'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;

?>