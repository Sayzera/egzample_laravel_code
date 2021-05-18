<?php

namespace App\Http\Controllers\Entegrasyon;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EntegrasyonController extends Controller
{
    private $json_data_temp;
    private $nebim_urun_resim_yolu = 'web/productimages/';
    private $lang = 1;

    /**
     * categories
     * categories_description
     * image_categories
     * products
     * products_description
     * products_images
     * images
     * inventory
     * products_to_categories
     */

    public function __construct()
    {
        $this->json_data_temp = $this->json_data();
        $this->read_arr($this->json_data());
    }

    public function index()
    {
       
       $this->kategoriEkle();
       $this->sup_kategori_ekle($this->json_data_temp);
       $this->alt_kategori_ekle($this->json_data_temp);
       $this->joinMainCategoriToProduct();
       $this->joinSubCategoriToProduct();        

    }

    public function kategoriEkle()
    {
        foreach ($this->json_data_temp as $key => $kategori) {

            $categori_check =  DB::table('categories')
                ->where('categories_slug', $this->permalink($kategori->Cat01Desc))
                ->get();
            // Kategori adları  Ekleme 
            if (count($categori_check) <= 0) {
                $categori_ekle = DB::table('categories')
                    ->insert([
                        'categories_slug' => $this->permalink($kategori->Cat01Desc),
                        'categories_status' => 1,

                    ]);
                $last_categori_id = DB::getpDO()->lastInsertId();
                // kategori desc tablosunada ekle 
                $categori_description = DB::table('categories_description')
                    ->insert([
                        'categories_id' => $last_categori_id,
                        'categories_name' => $kategori->Cat01Desc,
                        'categories_description' => $kategori->Cat01Desc,
                        'language_id' => $this->lang
                    ]);
                // Resimleri ekle 
                $categori_image = DB::table('image_categories')
                    ->insert([
                        'image_id' => $last_categori_id,
                        'image_type' => 'THUMBNAIL',
                        'height' => '250',
                        'width' => '250'
                    ]);
            }
        }
    }


    public function sup_kategori_ekle($products)
    {

        foreach ($products as $key => $product) {
    
                $main_categori = DB::table('categories')->where('categories_slug', $this->permalink($product->Cat01Desc))->get();
                // bu urun hangı kategoriye ait (main)
                $main_categori_id =   $main_categori[0]->categories_id;
                
                // bu sup daha once eklenmiş mi 
                $sup_kategori = DB::table('categories')->where('categories_slug', $this->permalink($product->Cat02Desc))->get();

                if(count($sup_kategori) == 0) {
                    // categories 
                    DB::table('categories')->insert(
                        [
                            'categories_slug' => $this->permalink($product->Cat02Desc),
                            'parent_id' =>  $main_categori_id
                        ]
                    );
                    $last_categori_id = DB::getpDO()->lastInsertId();
                    DB::table('categories_description')->insert(
                        [
                            'categories_id' => $last_categori_id,
                            'language_id' => $this->lang,
                            'categories_name' => $product->Cat02Desc,
                            'categories_description' => $product->Cat01Desc
                        ]
                    );
                }


           
        }
    }

    public function alt_kategori_ekle($products)
    {

        foreach ($products as $key => $product) {
    
                $main_categori = DB::table('categories')->where('categories_slug', $this->permalink($product->Cat02Desc))->get();
                // bu urun hangı kategoriye ait (main)
                $main_categori_id =   $main_categori[0]->categories_id;
                
                // bu sup daha once eklenmiş mi 
                $sup_kategori = DB::table('categories')->where('categories_slug', $this->permalink($product->Cat03Desc))->get();

                if(count($sup_kategori) == 0) {
                    // categories 
                    DB::table('categories')->insert(
                        [
                            'categories_slug' => $this->permalink($product->Cat03Desc),
                            'parent_id' =>  $main_categori_id
                        ]
                    );
                    $last_categori_id = DB::getpDO()->lastInsertId();
                    DB::table('categories_description')->insert(
                        [
                            'categories_id' => $last_categori_id,
                            'language_id' => $this->lang,
                            'categories_name' => $product->Cat03Desc,
                            'categories_description' => $product->Cat01Desc
                        ]
                    );
                }
                if(isset($last_categori_id)) {
                    $this->urun_ekle($last_categori_id,$product);

                }

        }
    }


    public function urun_ekle($categori_id_new,$urun)
    {
            $urun_kontrol = DB::table('products')->where('barkod_code', $urun->ItemCode)->get();
            // urun daha onceden eklenmiş mi 
            if (count($urun_kontrol) <= 0) {
                // urun resmini ekle 
                $urun_resim_ekle = DB::table('images')->insert(
                    [
                        'name' => $urun->Image1,
                        'user_id' => 1
                    ]
                );
                $image_id = DB::getpDO()->lastInsertId();


                // eklenen resmin idsi 
                DB::table('products')->insert(
                    [
                        'products_model' => $urun->ItemName,
                        'products_price' => $urun->Price7,
                        'is_feature' => 1,
                        'products_status' => 1,
                        'is_current' => 1,
                        'products_slug' => $this->permalink($urun->ItemName),
                        'products_image' => $image_id,
                        'products_type' => 0,
                        'products_tax_class_id' => 0,
                        'products_max_stock' => 9999,
                        'low_limit' => 0,
                        'barkod_code' => $urun->ItemCode,
                        'products_quantity' => 500,
                        'created_at' => date('Y-m-d h:i:s'),
                        'urun_resim_yolu' => $this->nebim_urun_resim_yolu . $urun->Image1,
                        'default_images' => $this->nebim_urun_resim_yolu . $urun->Image1,
                        'main_kategori' => $urun->Cat01Desc,
                        'sub_kategori' => $urun->Cat02Desc,
                        'alt_kategori' => $urun->Cat03Desc
                    ]
                );
                $urun_id = DB::getpDO()->lastInsertId();

                // Urunun stok durumunu ekle 
                $this->urun_stok($urun_id, $urun);

                // Urun acıklamasını ekle 
                DB::table('products_description')->insert(
                    [
                        'language_id' => $this->lang,
                        'products_id' => $urun_id,
                        'products_description' => $urun->ItemDesc,
                        'products_name' => $urun->ItemName,
                        'modal_images' => serialize([
                            $this->nebim_urun_resim_yolu . $urun->Image1,
                            $this->nebim_urun_resim_yolu . $urun->Image2,
                            $this->nebim_urun_resim_yolu . $urun->Image3,
                            $this->nebim_urun_resim_yolu . $urun->Image4
                        ])
                    ]
                );
                // Ek olan resimleri ekle 

                $images = (array) $urun;
                for ($i = 1; $i <= 4; $i++) {

                    DB::table('images')->insert([
                        'name' => $this->nebim_urun_resim_yolu . $images['Image' . $i],
                        'user_id' => 1
                    ]);

                    // yuklenen her urunun idsini almak için
                    $urunun_resmi_id = DB::getpDO()->lastInsertId();

                    DB::table('products_images')->insert([
                        'products_id' => $urun_id,
                        'image' => $urunun_resmi_id,
                        'sort_order' => $i
                    ]);
                }

                // // Gelen bu urun hangi kategoriye ait 
                // $categories = DB::table('categories')
                //     ->where('categories_slug', $this->permalink($urun->Cat01Desc))
                //     ->get();

                // $categori_id_new = $categories[0]->categories_id;


                DB::table('products_to_categories')->insert([
                    'products_id' => $urun_id,
                    'categories_id' => $categori_id_new
                ]);



            } else {
                // Urun daha önceden eklenmiş 

                //  $this->read_arr($urun_kontrol);

                $this->urun_guncelle($urun_kontrol, $urun);
            }
    }

    public function joinMainCategoriToProduct() {
       // ana kategori isimleri 
       $main_categories =  DB::table('products')->select('main_kategori','products_id')->groupBy('main_kategori')->get();
       $prodcuts = DB::table('products')->get();
       // her bir urunu bir kategorı adıyla ılışkılendirdim 
       foreach ($main_categories as $key => $categori_name) {
       $categori_id = DB::table('categories')->where('categories_slug',$this->permalink($categori_name->main_kategori))->get();
       // ilişki sağlanılacak categori id 
            foreach ($prodcuts as $key => $product) {
                // aynı urun ve aynı kategori 1 kez eklensin 
               $kontrol =  DB::table('products_to_categories')->where('products_id',$product->products_id)
                ->where('categories_id',$categori_id[0]->categories_id)
                ->get();

                if(count($kontrol) == 0) {
                    DB::table('products_to_categories')->insert([
                        'products_id' => $product->products_id,
                        'categories_id' => $categori_id[0]->categories_id
                    ]);
                }

            
            }
       }

    }

    public function joinSubCategoriToProduct() {
        // ana kategori isimleri 
        $main_categories =  DB::table('products')->select('sub_kategori','products_id')->groupBy('sub_kategori')->get();
        $prodcuts = DB::table('products')->get();
        // her bir urunu bir kategorı adıyla ılışkılendirdim 
        foreach ($main_categories as $key => $categori_name) {
        $categori_id = DB::table('categories')->where('categories_slug',$this->permalink($categori_name->sub_kategori))->get();
        // ilişki sağlanılacak categori id 
             foreach ($prodcuts as $key => $product) {
                 // aynı urun ve aynı kategori 1 kez eklensin 
                $kontrol =  DB::table('products_to_categories')->where('products_id',$product->products_id)
                 ->where('categories_id',$categori_id[0]->categories_id)
                 ->get();
 
                 if(count($kontrol) == 0) {
                     DB::table('products_to_categories')->insert([
                         'products_id' => $product->products_id,
                         'categories_id' => $categori_id[0]->categories_id
                     ]);
                 }
 
             
             }
        }
 
     }
 


    


    public function urun_guncelle($urun_kontrol, $json_data)
    {
        $urun_kontrol = $urun_kontrol[0];
        DB::table('products')

            ->where('products_id', $urun_kontrol->products_id)
            ->update([
                'urun_resim_yolu' => $this->nebim_urun_resim_yolu . $json_data->Image1,
                'default_images' =>  $this->nebim_urun_resim_yolu . $json_data->Image1
            ]);
    }


    public function urun_stok($urun_id, $product)
    {
        DB::table('inventory')->insert(
            [
                'admin_id' => 1,
                'added_date' => date('d M Y H:i:s'),
                'reference_code' => 'no reference',
                'stock' => 100,
                'products_id' => $urun_id,
                'stock_type' => 'in',
                'purchase_price' => $product->Price7,
                'created_at' => date('d M Y H:i:s')
            ]
        );
    }



    public function permalink($str, $options = array())
    {
        $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => true
        );
        $options = array_merge($defaults, $options);
        $char_map = array(
            // Latin
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
            'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y',
            // Latin symbols
            '©' => '(c)',
            // Greek
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
            'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
            'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
            'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
            'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
            'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
            'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
            // Turkish
            'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
            'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',
            // Russian
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya',
            // Ukrainian
            'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
            'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
            // Czech
            'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
            'ž' => 'z',
            // Polish
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z',
            // Latvian
            'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
            'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
            'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
            'š' => 's', 'ū' => 'u', 'ž' => 'z'
        );
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
        $str = trim($str, $options['delimiter']);
        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }


    public function json_data()
    {
        //$json_url = "http://185.46.55.70:1000/(S(e3cswqpitmf3k3md5jexocd5))/IntegratorService/Connect";
        $json_url = "http://185.46.55.98:8888/(S(u0jj5jd5qswkpeijbwh5ygoz))/IntegratorService/Connect";
        $json_file = file_get_contents($json_url, true);
        $jdosya = json_decode($json_file);
        // echo "<pre>";
        // print_r($jdosya);
        // echo "</pre>";

        $token = $jdosya->SessionID;

        //$json_url_dosya_cek = "http://185.46.55.98:8888/(S(".$token."))/IntegratorService/RunProc?{%20%22ProcName%22:%20%22sp_ProductPriceAndInventoryPROXIMA%22,%20%22Parameters%22:%20[%20{%20%22Name%22:%20%22LangCode%22,%20%22Value%22:%20%22TR%22%20}%20]%20}";
        $json_url_dosya_cek = "http://185.46.55.98:8888/(S(" . $token . "))/IntegratorService/RunProc/BCF69980A2764B2EBB48A663CA256A63?{%22ProcName%22:%22usp_GetProductPriceAndInventory_Proxima%22}";
        //$json_url_dosya_cek = "http://185.46.55.98:8888/IntegratorService/RunProc/BCF69980A2764B2EBB48A663CA256A63?{%22ProcName%22:%22usp_GetProductPriceAndInventory_Proxima%22}";
        $json_file_dosya_cek = file_get_contents($json_url_dosya_cek, true);
        $jdosya_cek = json_decode($json_file_dosya_cek);
        return $jdosya_cek;
    }

    public function read_arr($jdosya_cek)
    {
        echo "<pre>";
        print_r($jdosya_cek);
        echo "</pre>";
    }
}
