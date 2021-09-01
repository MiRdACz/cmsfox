<?php
namespace App\Model;

use Nette;
use Nette\Database\Table\Selection;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class EshopModel
{
    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function getPayment()
    {
        return $this->database->query('SELECT * FROM payment');
    }

    public function transport()
    {
        return $this->database->table('transport');
    }

    public function transportGet($id)
    {
        return $this->database->table('transport')->get($id);
    }

    public function transportDelete($id)
    {
        return $this->database->table('transport')->where('id', $id)->delete();
    }

    public function getTransportSelect($id)
    {
        return $this->database->query('SELECT id,name,price,apiKey FROM transport WHERE id = ?', $id)->fetch();
    }

    public function akce()
    {
        return $this->database->table('akce')->get(1);
    }

    public function getPaymentSelect($id)
    {
        return $this->database->query('SELECT * FROM payment WHERE id = ?', $id)->fetch();
    }
    public function skladInsert($id,$sklad)
    {
        $this->database->beginTransaction();
        try {
        $this->database->table('sklad')->where('id', $id)->update([
            'sklad' => $sklad
        ]);
        $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function deleteZbozi($id,$objId)
    {
        $this->database->beginTransaction();
        try {
            $product = $this->database->table('objednavka_produkt')->where('objednavka_id = ?', $objId);
            foreach ($product as $item) {

                $zbozi = $this->database->table('product')->where('name ?', $item['name']);

                foreach ($zbozi as $polozka) {
                    foreach ($polozka->related('sklad') as $sklad) {
                        if ($polozka['name'] == $item['name']) {
                            $zboziObj = $item['pocet'];
                            $skladOrig = $sklad->sklad;
                            $skladNovy = $skladOrig + $zboziObj;
                            $this->database->query('UPDATE sklad SET sklad = ? WHERE product_id = ?', $skladNovy, $polozka->id);
                        }
                    }
                }
            }
            $this->database->query('DELETE FROM objednavka_produkt WHERE id = ?', $id);
            $this->database->commit();

        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function ulozZbozi($name,$id,$pocet,$price)
    {
        $this->database->beginTransaction();

        try {
            $pocetVobj = $this->database->table('objednavka_produkt')->where('id', $id)->fetch();
            $puvodniPocet = $pocetVobj->pocet;
            $prod = $this->database->table('product');
            if ($puvodniPocet > $pocet) {
                $naSklad = $puvodniPocet - $pocet;
                foreach ($prod as $produkt) {
                    if ($name == $produkt->name) {
                        $dataSklad = $this->database->table('sklad')->where('product_id', $produkt->id)->fetch();
                        $sklad = $dataSklad->sklad + $naSklad;
                        $this->database->table('sklad')->where('product_id', $produkt->id)->update([
                            'sklad' => $sklad
                        ]);
                    }
                }
            }
            if ($puvodniPocet < $pocet) {
                $naSklad = $pocet - $puvodniPocet;
                foreach ($prod as $produkt) {
                    if ($name == $produkt->name) {
                        $dataSklad = $this->database->table('sklad')->where('product_id', $produkt->id)->fetch();
                        $sklad = $dataSklad->sklad - $naSklad;
                        $this->database->table('sklad')->where('product_id', $produkt->id)->update([
                            'sklad' => $sklad
                        ]);
                    }
                }
            }

            $this->database->table('objednavka_produkt')->where('id', $id)->update([
                'name' => $name,
                'pocet' => $pocet,
                'price' => $price
            ]);

            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function objQuery()
    {
        return $this->database->table('objednavka')->order('datum DESC');
    }
    public function objGet($id)
    {
      // return $this->database->table('objednavka')->where('id',$id);
      return $this->database->table('objednavka')->get($id);
      // return $this->database->query('SELECT * FROM objednavka WHERE id = ?',$id);
    }
    public function objEx($id)
    {
        return $this->database->table('objednavka')->where($id);
    }
    public function pdfRelated($id)
    {
        $pdfObj = $this->database->query('SELECT * FROM objednavka WHERE id = ?',$id);
        $arr = '';
        foreach ($pdfObj as $pdf) {
            foreach ($pdf->related('objednavka_produkt') as $objPro){
                $arr .= $objPro;
            }
        }
        return $arr;
    }
    public function cenaNoveObj($id){
        return $this->database->query('SELECT round(price * pocet *(1+dph/100),0) as total_price FROM objednavka_produkt WHERE objednavka_id =?', $id)->fetchAll();
    }
    public function cenaObj($id){
        return $this->database->query('SELECT round(dopravaCena + platbaCena) as cenaDohromady FROM objednavka WHERE id =?', $id)->fetch();
    }
    public function productName()
    {
     return $this->database->table('product')->order('name ASC');
    }
    public function ulozCena($id,$doprava,$platba)
    {
        $this->database->beginTransaction();
        try {
        $this->database->table('objednavka')->where('id', $id)->update([
            'dopravaCena' => $doprava,
            'platbaCena' => $platba
        ]);
        $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function objednavka($values, $doprava, $cena_celkem, $akce)
    {
        $this->database->beginTransaction();
        try {
            $i = $this->database->table('objednavka')->insert([
                'jmeno' => $values->jmeno,
                'prijmeni' => $values->prijmeni,
                'ulice' => $values->ulice,
                'mesto' => $values->mesto,
                'psc' => $values->psc,
                'telefon' => $values->telefon,
                'email' => $values->email,
                'doprava' => $doprava,
                'dopravaCena' => $this::getTransportSelect($values->transport)->price,
                'platba' => $this::getPaymentSelect($values->payment)->name,
                'platbaCena' => $this::getPaymentSelect($values->payment)->price,
                'stav' => 'nová',
                'cena_celkem' => $cena_celkem + ($this::getTransportSelect($values->transport)->price) + ($this::getPaymentSelect($values->payment)->price),
                'akce' => $akce,
                'poznamka' => $values->poznamka,
            ]);
            return $i->id;
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function objednavkaProdukt($value,$id)
    {

        return $this->database->table('objednavka_produkt')->insert([
            'name' => $value['title'],
            'price' => $value['price'],
            'pocet' => $value['pocet'],
            'dph' => $value['dph'],
            'objednavka_id' => $id,
        ]);

    }
    public function skladObj($value)
    {
        return $this->database->table('sklad')->where('product_id',$value['id']);
    }
    public function getTransport()
    {
        return $this->database->query('SELECT * FROM transport WHERE aktiv IS NULL')->fetchAll();
    }
    public function insertTransport($values)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('transport')->insert([
                'name' => $values->name,
                'price' => $values->price,
            ]);$this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function updateTransport($values,$id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('transport')->where('id', $id)->update([
                    'name' => $values->name,
                    'price' => $values->price,
            ]);$this->database->commit();
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
    }
    public function updateTransportZasilkovna($values)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('transport')->where('id', 0)->update([
                'name' => $values->name,
                'price' => $values->price,
                'apiKey' => $values->apiKey,
            ]);$this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function zasilkovna($values)
    {
        $this->database->beginTransaction();
        if($values == true){
            try {
                $this->database->table('transport')->where('id', 0)->update([
                    'aktiv' => 1,
                ]);$this->database->commit();
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
        }else{
            try {
                $this->database->table('transport')->where('id', 0)->update([
                    'aktiv' => null,
                ]);$this->database->commit();
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
        }

    }
    public function transNull()
    {
        return $this->database->query('SELECT * FROM transport WHERE id = ?',0)->fetch();
    }
    public function eshopProductCategory($id)
    {
        return $this->database->table('product_category')->where('product_id', $id)->fetch();
    }
    public function getUrlFromDatabaseProduct($url){
        return $this->database->table('product')->where('url', $url)->fetch();
    }
    public function getProduct($id){
        return $this->database->table('product')->get($id);
    }
    public function productPolozka($id)
    {
        return $this->database->table('objednavka_produkt')->where('objednavka_id', $id);
    }
    public function insertProductObj($produkt,$id)
    {
        $this->database->beginTransaction();
        try {
        $this->database->table('objednavka_produkt')->insert([
            'name' => $produkt['name'],
            'pocet' => 1,
            'price' => $produkt['price'],
            'dph' => $produkt['dph'],
            'objednavka_id' => $id
        ]);
        $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function categoryParrent($parent)
    {
        return $this->database->table('category')->where('parent_id', $parent);
    }
    public function insertProduct($values)
    {
        $this->database->beginTransaction();
        try {
        $post = $this->database->table('product')->insert([
            "name" => $values['name'],
            "price" => $values['price'],
            "dph" => $values['dph'],
            "title" => $values['title'],
            "url" => $values['url'],
            "content" => $values['content'],
            "img" => $values['img'],
            "sale" => $values['sale'],
        ]);
            $idProduct = $post->id;
            $this->database->table('sklad')->insert([
            "sklad" => $values['sklad'],
            "product_id" => $idProduct,
        ]);
            $this->database->table('product_category')->insert([
            "product_id" => $idProduct,
            "category_id" => $values['category'],
        ]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function checkCategory($id)
    {
        return $this->database->query('SELECT category_id FROM product_category WHERE category_id =?', $id)->fetch();
    }
    public function checkParent($id)
    {
        return $this->database->query('SELECT parent_id FROM category WHERE parent_id =?', $id)->fetch();
    }
    public function deleteCategory($id)
    {
       return $this->database->query('DELETE FROM category WHERE id = ?', $id);
    }
    public function insertCategoryEdit($postId,$values)
    {
        $post = $this->database->table('category')->get($postId);
        $post->update($values);
    }
    public function insertCategory($values)
    {
        return $this->database->table('category')->insert($values);
    }
    public function categorRoutery($id){
        return $this->database->table('category')->get($id);
    }
    public function categorSlug($slug){
        return $this->database->query('SELECT * FROM category WHERE slug = ?',$slug);
    }
    public function category($id){
        return $this->database->table('category')->where('id = ?',$id)->fetchAll();
    }
    public function sklad(){
        return $this->database->table('sklad');
    }
    public function skladId($id){
        return $this->database->table('sklad')->where('product_id', $id)->fetch();
    }
    public function findArticleEshopByIb($id){
        return $this->database->query('SELECT *, round(price*(1+dph/100),0) as total_price FROM product WHERE id=?', $id)->fetch();
    }
    public function findArticleEshopId($podle, bool $order, int $limit, $category){
        if($category != ''){
            return $this->database->query('SELECT prd.*, round(prd.price*(1+prd.dph/100),0) as total_price, cat.category_id as category_id, cat.product_id as product_id, sk.sklad as sklad FROM product as prd JOIN product_category as cat ON product_id = prd.id JOIN sklad as sk ON sk.product_id = prd.id WHERE category_id = ? ORDER BY ? LIMIT ?',$category, [$podle => $order,],$limit);
        }else{
            if($order == 0){$order=false;}else{$order=true;}
            return $this->database->query('SELECT prd.*, round(prd.price*(1+dph/100),0) as total_price,sk.sklad as sklad FROM product as prd JOIN sklad as sk ON sk.product_id = prd.id ORDER BY ? LIMIT ?',[$podle => $order,],$limit);
        }
    }
    public function query()
    {
        return $this->database->query('SELECT * FROM category')->fetchAll();
    }
    //routovani cesty
    public function pripravCestu($query,$id){

        $menus = array(
            'items' => array(),
            'parents' => array()
        );
        foreach ($query as $items ) {
            // Create current menus item id into array
            $menus['items'][$items['id']] = $items;
            // Creates list of all items with children
            $menus['parents'][$items['parent_id']][] = $items['name'];
        }
        return $this::parrentProcesCesta($id, $menus,'');
    }
    public function parrentProcesCesta($parent, $menu, $separate)
    {
        $html = '';
        if (isset($menu['items'][$parent])) {
                $html .= $this->parrentProcesCesta($menu['items'][$parent]['parent_id'],$menu,'/');
            if (isset($menu['parents'][$menu['items'][$parent]['parent_id']])) {
                $html .= Strings::webalize($menu['items'][$parent]['name']).$separate;
            }
        }
        return $html;
    }

    public function priprav($query,$id){

        $menus = array(
            'items' => array(),
            'parents' => array()
        );
        foreach ($query as $items ) {
            // Create current menus item id into array
            $menus['items'][$items['id']] = $items;
            // Creates list of all items with children
            $menus['parents'][$items['parent_id']][] = $items['id'];
        }
        return $this::parrentProces($id, $menus);
    }

    public function parrentProces($parent, $menu)
    {
        $html = '';
        if (isset($menu['items'][$parent])) {
            $html.= $menu['items'][$parent]['parent_id'].',';
            $html .= $this->parrentProces($menu['items'][$parent]['parent_id'],$menu);
        }
        return $html;
    }

    public function categoryStrom($query,$baseUrl,$category,$cat){

        $menus = array(
            'items' => array(),
            'parents' => array()
        );
        foreach ($query as $items ) {
            // Create current menus item id into array
            $menus['items'][$items['id']] = $items;
            // Creates list of all items with children
            $menus['parents'][$items['parent_id']][] = $items['id'];
        }
        // Print all tree view menus
        return $this::createTreeView(0, $menus,'','',$baseUrl,$category,$cat);
    }

    function createTreeView($parent, $menu,$space,$odkaz,$baseUrl,$category,$cat) {

        $slugs = explode(',', $category);

        $html = '';

        if(isset($menu['items'][$parent])){
            $odkaz .= $menu['items'][$parent]['name'].' ';
        }

        if (isset($menu['parents'][$parent])) {
            $space .= '&nbsp;&nbsp;';

            foreach ($menu['parents'][$parent] as $itemId) {

                if(!isset($menu['parents'][$itemId])) {
                    $html .= '<div>' . $space;
                    $html .=  '<a href="'.$baseUrl.'/eshop/'.Strings::webalize($menu['items'][$itemId]['id']) . '" class="border-0 pl-1 odkazCat ';
                    if($menu['items'][$itemId]['id'] == $cat){
                        $html .= 'text-success';
                    }
                    $html .= '" id="' . $menu['items'][$itemId]['id'] . '" >' . $menu['items'][$itemId]['name'] . '</a></div>';
                }

                if(isset($menu['parents'][$itemId])) {

                    if($parent == 0){
                        $color = 'bg-dark navbar-dark text-white';
                        $space = '&nbsp;';
                    }else{
                        $color = 'py-1';
                    }

                    $html .= '<a id="'.$menu['items'][$itemId]['id'].'" class="laps ';

                    $html .= $color.' list-group-item list-group-item-action px-1" data-bs-toggle="collapse" href="#collapseCategory_'.$menu['items'][$itemId]['id'].'" >'.$space.$menu['items'][$itemId]['name'].' <span id="icona_'.$menu['items'][$itemId]['id'].'" class="float-end fas ';
                    //aktivni ikona
                    $icona = 'fa-angle-down ';
                    foreach($slugs as $index => $s){
                        if($s != 0){
                            if($menu['items'][$itemId]['id'] == $s){
                                $icona = 'fa-angle-up ';
                            }
                        }
                    }

                    $html .= $icona.'fa-fw p-1"></span></a>';

                    $html .= '<div id="collapseCategory_'.$menu['items'][$itemId]['id'].'" class="collapse ';
                    //aktivni collapse kategorie
                    foreach($slugs as $s){
                        if($menu['items'][$itemId]['id'] == $s && $s != 0){
                            $html .=' show';
                        }
                    }
                    $html .= '">';
                    $html .= $this->createTreeView($itemId, $menu, $space,$odkaz,$baseUrl,$category,$cat);
                    $html .= '</div>';
                }
            }
        }
        return $html;
    }

    public function categoryStromEdit(){

        $query = $this->database->query('SELECT * FROM category');
        $menus = array(
            'items' => array(),
            'parents' => array()
        );

        while ($items = $query->fetch() ) {
            // Create current menus item id into array
            $menus['items'][$items['id']] = $items;
            // Creates list of all items with children
            $menus['parents'][$items['parent_id']][] = $items['id'];
        }
        // Print all tree view menus
        return $this::createTreeViewEdit(0, $menus);
    }
    function createTreeViewEdit($parent, $menu, $space='') {

        $html = '';

        if (isset($menu['parents'][$parent])) {
            $space .= '&nbsp;&nbsp;&nbsp;';
            foreach ($menu['parents'][$parent] as $itemId) {
                if(!isset($menu['parents'][$itemId])) {
                    $html .= '<tr><td><div class="row"><div class="col"><p class="m-0 pt-1">'.$space.$menu['items'][$itemId]['name'].
                        '</p></div><div class="col text-end"><a class="btn text-success ms-3" href="edit-category?id='.$menu['items'][$itemId]['id'].'">editovat</a>
                <a class="btn text-danger ms-3 " href="delete-category?id='.$menu['items'][$itemId]['id'].'">smazat</a></div></div>
                </td></tr>';
                }
                if(isset($menu['parents'][$itemId])) {
                    if($parent == 0){
                        $space = '';
                        $color = 'fw-bold';

                    }else{
                        $color ='';
                    }
                    $html .= '<tr><td><div class="row '.$color.'"><div class="col"><p class="m-0 pt-1">'.$space.$menu['items'][$itemId]['name'].'</p></div><div class="col text-end">
                <a class="btn text-success ms-3" href="edit-category?id='.$menu['items'][$itemId]['id'].'">editovat</a>
                <a class="btn text-danger ms-3" href="delete-category?id='.$menu['items'][$itemId]['id'].'">smazat</a></div></div></td></tr>';
                    $html .= $this->createTreeViewEdit($itemId, $menu, $space);
                }
            }
        }

        return $html;
    }

    public function searchProductWhisperer($hledat)
    {
        return $this->database->query('SELECT title,content,url,name,img FROM product WHERE MATCH(content,name) AGAINST (? IN NATURAL LANGUAGE MODE) LIMIT 5', $hledat)->fetchAll();
    }

}