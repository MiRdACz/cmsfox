<?php
namespace App\Model;

use Nette;
use Nette\Utils\Image;

class ShortCodeManager
{
    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function getSlider(){
        $galerie = $this->database->table('slider');
        if(!$galerie){
            $this->error('Slider nebyl nalezen');
        }
        return $galerie;
    }

    public function getGalerie(){
        $galerie = $this->database->table('galerie');
        if(!$galerie){
            $this->error('Galerie nebyla nalezena');
        }
        return $galerie;
    }
    public function getVypisTitle($url)
    {
        $article = $this->database->table('article')->where('url',$url)->fetch();
        return $article['title'];
    }
    public function getVypis($url)
    {
        $array[] = '';
        $article = $this->database->table('article')->where('url',$url)->fetch();
        foreach($article->related('article.parrent_id') as $index => $ar){
            $array[$index] = '<a href="'.$ar['url'].'" >'.$ar['title'].'</a>';
        }
        return $array;
    }
    public function getGalerieArray($id)
    {
        return $this->database->table('galerie')->get($id);
    }
    public function getSliderArray($id)
    {
        return $this->database->table('slider')->get($id);
    }
    public function getSliderImgText(int $id)
    {
        return $this->database->table('img_slider')->get($id);
    }
    public function getGalerieImg(int $id)
    {
        $img = $this->database->table('img_galerie')->where('galerie_id = ?',$id);
        if(!$img){
            $this->error('Img galerie nebyla nalezena');
        }
        return $img;
    }
    public function getSliderImg(int $id)
    {
        $img = $this->database->table('img_slider')->where('slider_id = ?',$id);
        if(!$img){
            $this->error('Img galerie nebyla nalezena');
        }
        return $img;
    }
    public function getSliderId(int $id)
    {
        try {
            $galerie = $this->database->table('slider')->where('id = ?', $id);
            return $galerie;
        }catch (\PDOException $e){
            throw $e;
        }
    }
    public function getGalerieId(int $id)
    {
        try {
            $galerie = $this->database->table('galerie')->where('id = ?', $id);
            return $galerie;
        }catch (\PDOException $e){
            throw $e;
        }
    }
    public function timeSlider(string $time,int $id){

        $this->database->beginTransaction();
        try {
            $this->database->table('slider')->where(['id' => $id])->update(['time' => $time]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function renameGalerie(string $name,int $id){
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $this->database->table('galerie')->where(['id' => $id])->update(['name' => $name, 'code' => $code]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function renameSlider(string $name,int $id){
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $this->database->table('slider')->where(['id' => $id])->update(['name' => $name, 'code' => $code]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }

    }
    public function galeriePlusImg(string $content, int $idGal)
    {
        $this->database->beginTransaction();
        try {
        $obj = strtr($content, '[]"', '   ');
        $img = str_replace(' ', '', $obj);
        $ar = explode(",", $img);

        foreach ($ar as $obsah) {
            $image = Image::fromFile('img/' . $obsah);
            $this->database->table('img_galerie')->insert([
                'content' => $obsah, 'galerie_id' => $idGal,'width' => $image->getWidth(),'height'=> $image->getHeight()
            ]);
        }
        $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function sliderPlusImg(string $content, int $idGal)
    {
        $this->database->beginTransaction();
        try {
            $obj = strtr($content, '[]"', '   ');
            $img = str_replace(' ', '', $obj);
            $ar = explode(",", $img);

            foreach ($ar as $obsah) {
                $image = Image::fromFile('img/' . $obsah);
                $this->database->table('img_slider')->insert([
                    'content' => $obsah, 'slider_id' => $idGal,'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function insertGalerie(string $name, string $content)
    {
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $row = $this->database->table('galerie')->insert(['name' => $name, 'code' => $code]);

            $obj = strtr($content, '[]"', '   ');
            $img = str_replace(' ', '', $obj);
            $ar = explode(",", $img);

            foreach ($ar as $obsah) {
                $image = Image::fromFile('img/' . $obsah);
                $this->database->table('img_galerie')->insert([
                    'content' => $obsah, 'galerie_id' => $row['id'],'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function insertSlider(string $name,string $content,string $time)
    {
        $code = '[[' . $name . ']]';
        $this->database->beginTransaction();
        try {
            $row = $this->database->table('slider')->insert(['name' => $name, 'code' => $code, 'time' => $time]);

            $obj = strtr($content, '[]"', '   ');
            $img = str_replace(' ', '', $obj);
            $ar = explode(",", $img);

            foreach ($ar as $obsah) {
                $image = Image::fromFile('img/' . $obsah);
                $this->database->table('img_slider')->insert([
                    'content' => $obsah, 'slider_id' => $row['id'],'width' => $image->getWidth(),'height'=> $image->getHeight()
                ]);
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function addTextSlider(int $id,string $text)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('img_slider')->where(['id' => $id])->update(['text' => $text]);
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
        $this->database->table('img_galerie')->where(['galerie_id' => $id])->delete();
        $this->database->table('galerie')->where(['id' => $id])->delete();
        $this->database->commit();
        return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteSlider($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('img_slider')->where(['slider_id' => $id])->delete();
            $this->database->table('slider')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteGalerieImg($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('img_galerie')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteSliderImg($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('img_slider')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
    public function deleteInputFormular($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('shortcodeformular')->where(['id' => $id])->delete();
            $this->database->commit();
            return true;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
    }


}