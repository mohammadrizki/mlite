<?php

namespace Plugins\Master\Src;

use Systems\Lib\QueryWrapper;

class Ktpl
{

    protected function db($table)
    {
        return new QueryWrapper($table);
    }

    public function getIndex()
    {
      $totalRecords = $this->db('mlite_ktpl')->count();
      $offset         = 10;
      $return['halaman']    = 1;
      $return['jml_halaman']    = ceil($totalRecords / $offset);
      $return['jumlah_data']    = $totalRecords;

      $return['list'] = $this->db('mlite_ktpl')
        ->desc('kode_ktpl')
        ->limit(10)
        ->toArray();

      return htmlspecialchars_array($return);
    }

    public function anyForm()
    {
        if (isset($_POST['kode_ktpl'])){
          $return['form'] = $this->db('mlite_ktpl')->where('kode_ktpl', $_POST['kode_ktpl'])->oneArray();
        } else {
          $return['form'] = [
            'kode_ktpl' => '',
            'nama_ktpl' => '',
            'has_modifier' => 0,
            'modifier_count' => 0,
            'status' => 'Aktif'
          ];
        }

        return htmlspecialchars_array($return);
    }

    public function anyDisplay()
    {
        $perpage = '10';
        $totalRecords = $this->db('mlite_ktpl')->count();
        $offset         = 10;
        $return['halaman']    = 1;
        $return['jml_halaman']    = ceil($totalRecords / $offset);
        $return['jumlah_data']    = $totalRecords;

        $return['list'] = $this->db('mlite_ktpl')
          ->desc('kode_ktpl')
          ->offset(0)
          ->limit($perpage)
          ->toArray();

        if(isset($_POST['cari'])) {
          $query = $this->db('mlite_ktpl')
            ->like('kode_ktpl', '%'.htmlspecialchars($_POST['cari'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'%')
            ->orLike('nama_ktpl', '%'.htmlspecialchars($_POST['cari'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'%');
            
          $jumlah_data = $query->count();
          
          $return['list'] = $query->desc('kode_ktpl')
            ->offset(0)
            ->limit($perpage)
            ->toArray();
            
          $return['jumlah_data'] = $jumlah_data;
          $return['jml_halaman'] = ceil($jumlah_data / $offset);
        }
        if(isset($_POST['halaman'])){
          $offset     = (($_POST['halaman'] - 1) * $perpage);
          $return['list'] = $this->db('mlite_ktpl')
            ->desc('kode_ktpl')
            ->offset($offset)
            ->limit($perpage)
            ->toArray();
          $return['halaman'] = $_POST['halaman'];
        }

        return htmlspecialchars_array($return);
    }

    public function postSave()
    {
      if (!$this->db('mlite_ktpl')->where('kode_ktpl', $_POST['kode_ktpl'])->oneArray()) {
        $query = $this->db('mlite_ktpl')->save($_POST);
      } else {
        $query = $this->db('mlite_ktpl')->where('kode_ktpl', $_POST['kode_ktpl'])->save($_POST);
      }
      return $query;
    }

    public function postHapus()
    {
      return $this->db('mlite_ktpl')->where('kode_ktpl', $_POST['kode_ktpl'])->delete();
    }

}
