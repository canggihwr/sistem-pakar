<?php

namespace App\Http\Controllers;

use App\Models\Diagnosa;
use App\Http\Requests\StoreDiagnosaRequest;
use App\Http\Requests\UpdateDiagnosaRequest;
use App\Models\Gejala;
use App\Models\Keputusan;
use App\Models\Kode_Gejala;
use App\Models\KondisiUser;
use App\Models\TingkatDepresi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\map;
use function PHPSTORM_META\type;

class DiagnosaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = [
            'gejala' => Gejala::all(),
            'kondisi_user' => KondisiUser::all()
        ];

        return view('clients.form_diagnosa', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }











    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDiagnosaRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDiagnosaRequest $request)
    {
        $kondisi = $request->post('kondisi');
        // dd($kondisi);
        $kodeGejala = [];
        $bobotPilihan = [];
        foreach ($kondisi as $key => $val) {
            if ($val != "#") {
                echo "key : $key, val : $val";
                echo "<br>";
                array_push($kodeGejala, $key);
                array_push($bobotPilihan, array($key => $val));
            }
        }

        $depresi = TingkatDepresi::all();
        $cf = 0;
        // penyakit
        $arrGejala = [];
        for ($i = 0; $i < count($depresi); $i++) {
            $cfArr = [
                "cf" => [],
                "kode_depresi" => []
            ];
            $res = 0;
            $ruleSetiapDepresi = Keputusan::whereIn("kode_gejala", $kodeGejala)->where("kode_depresi", $depresi[$i]->kode_depresi)->get();
            // dd($ruleSetiapDepresi);
            if (count($ruleSetiapDepresi) > 0) {
                foreach ($ruleSetiapDepresi as $ruleKey) {
                    $cf = $ruleKey->mb - $ruleKey->md;
                    print "<br> cf : $cf <br>";
                    array_push($cfArr["cf"], $cf);
                    array_push($cfArr["kode_depresi"], $ruleKey->kode_depresi);
                }
                $res = $this->getGabunganCf($cfArr);
                // dd($res);
                // print "<br> res : $res <br>";
                array_push($arrGejala, $res);
            } else {
                continue;
            }
        }
        echo "<br> arrGejala : ";
        print_r($arrGejala);
        echo "<br>";

        $diagnosa_id = uniqid();
        $ins =  Diagnosa::create([
            'diagnosa_id' => strval($diagnosa_id),
            'data_diagnosa' => json_encode($arrGejala),
            'kondisi' => json_encode($bobotPilihan)
        ]);
        // dd($ins);
        return redirect()->route('spk.result', ["diagnosa_id" => $diagnosa_id]);
    }

    public function getGabunganCf($cfArr)
    {
        echo "<br> cfArr : ";
        print_r($cfArr);
        echo "<br>";
        // dd($cfArr);
        if (!$cfArr["cf"]) {
            return 0;
        }
        if (count($cfArr["cf"]) == 1) {
            return [
                "value" => strval($cfArr["cf"][0]),
                "kode_depresi" => $cfArr["kode_depresi"][0]
            ];
        }
        $cfoldGabungan = $cfArr["cf"][0];

        for ($i = 0; $i < count($cfArr["cf"]) - 1; $i++) {
            $cfoldGabungan = $cfoldGabungan + $cfArr["cf"][$i + 1] * (1 - $cfoldGabungan);
        }
        echo "<br>cfGabungan return : $cfoldGabungan";
        echo "<br> cfArr kode_depresi : ";
        print_r($cfArr["kode_depresi"]);
        echo "<br>";

        return [
            "value" => "$cfoldGabungan",
            "kode_depresi" => $cfArr["kode_depresi"][0]
        ];
    }

    public function diagnosaResult($diagnosa_id)
    {
        $diagnosa = Diagnosa::where('diagnosa_id', $diagnosa_id)->first();
        $gejala = json_decode($diagnosa->kondisi, true);
        $data_diagnosa = json_decode($diagnosa->data_diagnosa, true);
        // dd($data_diagnosa);
        $int = 0.0;
        $diagnosa_dipilih = [];
        foreach ($data_diagnosa as $val) {
            // print_r(floatval($val["value"]));
            if (floatval($val["value"]) > $int) {
                $diagnosa_dipilih["value"] = floatval($val["value"]);
                $diagnosa_dipilih["kode_depresi"] = TingkatDepresi::where("kode_depresi", $val["kode_depresi"])->first();
                $int = floatval($val["value"]);
            }
        }
        // dd($diagnosa_dipilih);

        $kodeGejala = [];
        foreach ($gejala as $key) {
            foreach ($key as $value) {
                array_push($kodeGejala, array_search($value, $key));
            }
        }
        $kode_depresi = $diagnosa_dipilih["kode_depresi"]->kode_depresi;
        // dd($kode_depresi);
        // dd($kodeGejala);
        // dd($gejala);
        $pakar = Keputusan::whereIn("kode_gejala", $kodeGejala)->where("kode_depresi", $kode_depresi)->get();
        // dd($pakar);



        // dd($diagnosa_dipilih["kode_depresi"]);
        // dd($kondisi);
        // dd($gejala);
        $depresi = TingkatDepresi::all();
        $kondisi = KondisiUser::all();
        return view('clients.cl_diagnosa_result', [
            "diagnosa" => $diagnosa,
            "diagnosa_dipilih" => $diagnosa_dipilih,
            "gejala" => $gejala,
            "data_diagnosa" => $data_diagnosa,
            "depresi" => $depresi,
            "kodisi" => $kondisi,
            "pakar" => $pakar
        ]);
    }














    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Diagnosa  $diagnosa
     * @return \Illuminate\Http\Response
     */
    public function show(Diagnosa $diagnosa)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Diagnosa  $diagnosa
     * @return \Illuminate\Http\Response
     */
    public function edit(Diagnosa $diagnosa)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDiagnosaRequest  $request
     * @param  \App\Models\Diagnosa  $diagnosa
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDiagnosaRequest $request, Diagnosa $diagnosa)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Diagnosa  $diagnosa
     * @return \Illuminate\Http\Response
     */
    public function destroy(Diagnosa $diagnosa)
    {
        //
    }
}
