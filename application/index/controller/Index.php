<?php
namespace app\index\controller;

class Index
{
    public function index()
    {
        /*$keyMap = [
            'YDR1AMK392LIWEB874CS5ZU6NQGOHXFV',
            'IEF6NDBLK5M4X9YQ8ZASU7WVO12RCH3G',
            'HRZ71CAYQU2W385I6L4FGVNSEOBKXDM9',
            '4QXYCRMHEUN8DW13ZAO7F5L9ISBG6V2K',
            '3BKILOADZHERQ815CWGX2N64VUFMSY79',
            'NU2WZ7V495YLCE3MBXA8IRKDSQO6HFG1',
            '45XFNY9MCUELD8H23KIVZBOR67S1GAQW',
            'KWUBQC7EY4NMFS68AG95OIHZLRDV312X',
            'OM1AUDQSZHN62ELWB5GYVX3R74CK8IF9',
            'GC3UI94FA8SZBQRMNK15XEH762VYLWDO',
            'VHLUF739DRMCESGKQ1682IX5ONA4BYWZ',
            'C3UVANOB8EZD721FSQ6LIM5KR49YGHWX',
            'WM3RUFKZO5219GXSDIVHB867QNEALYC4',
            'EZS6FRVN78KQ2L9IDXB53MUAY41OHGCW',
            'LMY6HK7O5RQC1SX8NZWG39BAEF2UDIV4',
            'ZUW84YC67IHRESN3LBA2KQ1VXOM9FD5G',
        ];

        $ret = [
            'keyMap' => $keyMap
        ];

        die(json_encode($ret));*/

        $driver="/";
        echo 'C盘可用空间大小：'.countSize(disk_free_space($driver)) .', 总共：'.countSize(disk_total_space($driver));
    }
}
