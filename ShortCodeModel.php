<?php
namespace App\Model;

use Nette;
use Nette\Utils\Image;
use Nette\Utils\Strings;

class ShortCodeModel
{
    use Nette\SmartObject;

    /** @var Nette\Database\Explorer */
    private $database;

    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }
    public function getShortCodeOther(){
        $shortCode = $this->database->table('shortcode_other');
        if(!$shortCode){
            $this->error('ShortCode nebyl nalezen');
        }
        return $shortCode;
    }
    public function getShortCode(){
        $shortCode = $this->database->table('shortcode');
        if(!$shortCode){
            $this->error('ShortCode nebyl nalezen');
        }
        return $shortCode;
    }
    public function getArray(int $id)
    {
        return $this->database->table('shortcode')->get($id);
    }
    public function getShortCodeId(int $id){
        $shortCode = $this->database->table('shortcode')->where('id',$id);
        if(!$shortCode){
            $this->error('ShortCode nebyl nalezen');
        }
        return $shortCode;
    }
    public function insertSlider(string $name,string $content,string $time)
    {
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $row = $this->database->table('shortcode')->insert(['slider_name' => $name, 'slider_code' => $code, 'slider_time' => $time]);
            $arrayContent = Strings::split(Strings::replace($content,['~[\"]+~i' => '','~[\[]~' => '','~[\]]~' => '',]), '~,\s*~');
            foreach ($arrayContent as $img) {
                $image = Image::fromFile('img/' . $img);
                $this->database->table('slider_img')->insert([
                    'content' => $img, 'slider_id' => $row['id'],'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function timeSlider(string $time,int $id){

        $this->database->beginTransaction();
        try {
            $this->database->table('shortcode')->where(['id' => $id])->update(['slider_time' => $time]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; 
        }
    }
    public function renameSlider(string $name,int $id){
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $this->database->table('shortcode')->where(['id' => $id])->update(['slider_name' => $name, 'slider_code' => $code]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }

    }
    public function getSliderImgText(int $id)
    {
        return $this->database->table('slider_img')->get($id);
    }
    public function getSliderImgId(int $id)
    {
        try {
            $slider = $this->database->table('slider_img')->where('slider_id = ?', $id);
            return $slider;
        }catch (\PDOException $e){
            throw $e;
        }
    }
    public function getGalerieImg(int $id)
    {
        $img = $this->database->table('galerie_img')->where('galerie_id = ?',$id);
        if(!$img){
            $this->error('Img galerie nebyla nalezena');
        }
        return $img;
    }
    public function getSliderImg(int $id)
    {
        $img = $this->database->table('slider_img')->where('slider_id = ?',$id);
        if(!$img){
            $this->error('Obrazek slider nebyl nalezen');
        }
        return $img;
    }
    public function deleteSlider($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('slider_img')->where(['slider_id' => $id])->delete();
            $this->database->table('shortcode')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function addTextSlider(int $id,string $text)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('slider_img')->where(['id' => $id])->update(['text' => $text]);
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function sliderPlusImg(string $content, int $idSlider)
    {
        $this->database->beginTransaction();
        try {
             $image = Image::fromFile('img/' . $content);
             $this->database->table('slider_img')->insert([
                    'content' => $content, 'slider_id' => $idSlider,'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function deleteSliderImg($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('slider_img')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    // Galerie
    public function galeriePlusImg(string $content, int $idGal)
    {
        $this->database->beginTransaction();
        try {
            $arrayContent = Strings::split(Strings::replace($content,['~[\"]+~i' => '','~[\[]~' => '','~[\]]~' => '',]), '~,\s*~');
            foreach ($arrayContent as $img) {
                $image = Image::fromFile('img/' . $img);
                $this->database->table('galerie_img')->insert([
                    'content' => $img, 'galerie_id' => $idGal,'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteGalerieImg($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('galerie_img')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteGalerie($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('galerie_img')->where(['galerie_id' => $id])->delete();
            $this->database->table('shortcode')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function insertGalerie(string $name,string $content)
    {
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $row = $this->database->table('shortcode')->insert(['galerie_name' => $name, 'galerie_code' => $code]);
            $arrayContent = Strings::split(Strings::replace($content,['~[\"]+~i' => '','~[\[]~' => '','~[\]]~' => '',]), '~,\s*~');
            foreach ($arrayContent as $img) {
                $image = Image::fromFile('img/' . $img);
                $this->database->table('galerie_img')->insert([
                    'content' => $img, 'galerie_id' => $row['id'],'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function renameGalerie(string $name,int $id){
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $this->database->table('shortcode')->where(['id' => $id])->update(['galerie_name' => $name, 'galerie_code' => $code]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; 
        }
    }
    // kontakt
    public function editShortContact($array)
    {
        return $this->database->table('shortcode_other')->where('id',1)->update([
            "contact_email" => $array->contact_email,
            "contact_title" => $array->contact_title,
            "contact_content" => $array->contact_content,
            "contact_title_email" => $array->contact_title_email,
            "contact_send" => $array->contact_send,
        ]);
    }
    public function getShortContact()
    {
        return $this->database->table('shortcode_other')->get(1);
    }
    // formular
    public function getShortForm()
    {
        return $this->database->table('shortcode_other')->get(1);
    }
    public function getShortFormInput()
    {
        return $this->database->table('form_input');
    }
    public function deleteInputForm(int $id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('form_input')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    /** vypis nadrazene stranky */
    public function getLists($url)
    {
        $array[] = '';
        $page = $this->database->table('page')->where('url',$url)->fetch();
        $array['title'] = $page->title;
        foreach($page->related('page.parrent_id') as $index => $parrentPage){
            $array[$index] = '<a href="'.$parrentPage['url'].'" >'.$parrentPage['title'].'</a>';
        }
        return $array;
    }

}