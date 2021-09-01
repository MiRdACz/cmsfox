<?php
namespace App\Model;

use Nette;
use Nette\Database\Table\Selection;
use Nette\Utils\FileSystem;

class ArticleManager
{
    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }
    public function getShortFormular()
    {
        return $this->database->table('shortformular')->get(1);
    }
    public function getShortFormularLabel($input)
    {
        return $this->database->table('shortcodeformular')->where('input = ?',$input)->fetch();
    }
    public function getShortFormularInput()
    {
        return $this->database->table('shortcodeformular');
    }
    public function getShortKontakt()
    {
        return $this->database->table('shortkontakt')->get(1);
    }
    public function editShortKontakt($array)
    {
        return $this->database->table('shortkontakt')->where('id',1)->update([
            "email" => $array->email,
            "title" => $array->title,
            "content" => $array->content,
            "titleemail" => $array->titleemail,
            "sendbtn" => $array->sendbtn,
        ]);
    }
    // strom stranek v adminu
    public function articleStrom($query){
        $menus = array(
            'items' => array(),
            'parents' => array()
        );
        foreach ($query as $items ) {
            // Create current menus item id into array
            $menus['items'][$items['article_id']] = $items;
            $menus['itemsPar'][$items['article_id']] = $items['title'];
            // Creates list of all items with children
            $menus['parents'][$items['parrent_id']][] = $items['article_id'];
        }
        // Print all tree view menus
        return $this::createTreeViewArticle(null, $menus);
    }
    function createTreeViewArticle($parent, $menu,$space='') {
        $html = '<ul class="list list-group">';

        if (isset($menu['parents'][$parent])) {
            $space .= '&nbsp;&nbsp;';
            foreach ($menu['parents'][$parent] as $itemId) {
                if(!isset($menu['parents'][$itemId])) {
                    $html .= '<li class="list-group-item" id="'.$menu['items'][$itemId]['article_id'].'"><div class="row"><div class="col-5">';
                    $html .= '<span class="pe-3">'.$menu['items'][$itemId]['title'].'</span> | <a class="text-success px-3" title="editovat stránku" href="page-edit?id='.$menu['items'][$itemId]['article_id'].'"><i class="far fa-edit"></i></a> | ';
                    if($menu['items'][$itemId]['menu'] == true){
                        $html .= '<span class="badge bg-primary ms-3">Menu</span>  <a title="Odstranit z menu" class="" href="remove-menu?article_id='.$menu['items'][$itemId]['article_id'].'&url='.$menu['items'][$itemId]['url'].'">
                    <i class="fas fa-minus text-danger"></i></a>';
                    }else{
                        $html .='<a title="Přidat do menu" href="add-menu?article_id='.$menu['items'][$itemId]['article_id'].'&url='.$menu['items'][$itemId]['url'].'"><i class="fas fa-plus text-success ms-3"></i></a>';
                    }
                    $html .= '</div><div class="col">';
                    if($menu['items'][$itemId]['url'] =='blog' || $menu['items'][$itemId]['url'] == 'eshop'){
                        if(isset($menu['items'][$itemId]['parrent_id'])){
                            $html .= '<label class="small mb-0 pl-1 my-auto"><span class="badge bg-warning text-dark">Nadřazená stránka:</span> <b>'.$menu['itemsPar'][$menu['items'][$itemId]['parrent_id']].'</b></label>';
                        }$html .='<span class="badge bg-danger float-end"> Systémové stránky nelze smazat</span>';
                    }else{
                        $html .='<div class="row"><div class="input-group-prepend col-12">';
                        if(isset($menu['items'][$itemId]['parrent_id'])){
                            $html .= '<label class="small mb-0 pl-1 my-auto"><span class="badge bg-warning text-dark">Nadřazená stránka:</span> <b>'.$menu['itemsPar'][$menu['items'][$itemId]['parrent_id']].'</b></label>';
                        }
                        $html .=' </div>';
                        $html .='</div></div><div class="col text-end"><a class="text-danger smaz" href="delete-article?article_id='.$menu['items'][$itemId]['article_id'].'">Smazat <i class="far fa-trash-alt"></i></a>';
                    }

                    $html .= '</div></div></li>';

                }
                if(isset($menu['parents'][$itemId])) {

                    if($parent == null){
                        $space = '&nbsp;';
                    }
                    $html .= '<li class="list-group-item" id="'.$menu['items'][$itemId]['article_id'].'"><div class="row"><div class="col-5">';
                    $html .= '<span class="pe-3">'.$menu['items'][$itemId]['title'].'</span> | <a class="text-success px-3" title="editovat stránku" href="page-edit?id='.$menu['items'][$itemId]['article_id'].'"><i class="far fa-edit"></i></a> | ';
                    if($menu['items'][$itemId]['menu'] == true){
                        $html .= '<span class="badge bg-primary ms-3">Menu</span>  <a title="Odstranit z menu" class="" href="remove-menu?article_id='.$menu['items'][$itemId]['article_id'].'&url='.$menu['items'][$itemId]['url'].'">
                    <i class="fas fa-minus text-danger"></i></a>';
                    }else{
                        $html .='<a title="Přidat do menu" href="add-menu?article_id='.$menu['items'][$itemId]['article_id'].'&url='.$menu['items'][$itemId]['url'].'"><i class="fas fa-plus text-success ms-3"></i></a>';
                    }

                    $html .= '</div><div class="col-4"><label class="small mb-0 pl-1 my-auto"><span class="badge bg-success text-light">Nadřazená stránka</span></label></div>';
                    $html .= '</div></li>';
                    $html .= '<div class="ms-4">';
                    $html .= $this->createTreeViewArticle($itemId, $menu, $space);
                    $html .= '</div>';
                }
            }
        }
        $html .= '</ul>';
        return $html;
    }

//konec stranek
    public function editLogo($values)
    {
        $post = $this->database->table('logo')->where('id ?', 1);
        $post->update([
            'name'=>$values->name,
            'img'=>$values->img,
        ]);
    }
    public function searchPageWhisperer($hledat)
    {
        return $this->database->table('article')->where('title LIKE ?', '%'.$hledat.'%');
        //return $this->database->query('SELECT * FROM article WHERE MATCH(`title`) AGAINST (? IN NATURAL LANGUAGE MODE)', $hledat)->fetchAll();
    }
    public function editArticle($id,$values)
    {
        $post = $this->database->table('article')->get($id);
        $post->update([
            'title' => $values->title,
            'content' => $values->content,
            'url' => $values->url,
            'description' => $values->description,
            'parrent_id' => $values->parrent_id,
        ]);
    }
    public function  newArticle($values)
    {
        return $this->database->table('article')->insert([
            'title' => $values->title,
            'content' => $values->content,
            'url' => $values->url,
            'description' => $values->description,
        ]);
    }
    public function deleteArticle(int $article_id)
    {
        $this->database->table('article')->where('article_id', $article_id)->delete();
        return true;
    }
    public function listMenu(array $value)
    {
        foreach ($value as $poradi => $id) {
            $this->database->query('UPDATE article SET', ['poradi' => $poradi], 'WHERE article_id = ?', $id);
        }
    }
    public function pattern($parrentId,$id)
    {
        $this->database->query('UPDATE article SET', ['parrent_id' => $parrentId], 'WHERE article_id = ?', $id);
    }
    public function menuAdd(int $id)
    {
        return $this->database->table('article')->where('article_id', $id)->update(['menu' => 'true']);
    }
    public function menuRemove(int $id)
    {
        return $this->database->table('article')->where('article_id', $id)->update(['menu' => '']);
    }
    public function getMenu(){
        return $this->database->table('article')->order('poradi')->where('menu = ?','true');
    }
    public function menuArticle(){
        return $this->database->table('article')->where('parrent_id IS NULL AND menu = "true"');
    }
    public function getMenuParrent($parrent){
        return $this->database->query('SELECT * FROM article WHERE menu = "true" AND parrent_id = ?',$parrent)->fetchAll();
    }
    public function getArticles()
    {
        return $this->database->table('article')->order('menu DESC');
    }

    public function getArticlesMenu()
    {
        return $this->database->table('article')->order('menu DESC')->where('menu','');
    }

    public function getArticleId($id)
    {
        return $this->database->table('article')->get($id);
    }

    public function getPublicArticles()
    {
        return $this->database->table('posts')
            ->where('created_at < ', new \DateTime)
            ->order('created_at DESC');

    }
    public function getPublicPost()
    {
        return $this->database->table('posts')->order('created_at ASC');
    }

    public function getPostArticles($postId)
    {
        $post = $this->database->table('posts')->get($postId);
        if(!$post){
            $this->error('Stánka nebyla nalezena');
        }
        return $post;
    }

    public function getPostArticlesUrl($url)
    {
        $post = $this->database->table('posts')->where('url = ?',$url)->fetch();
        if(!$post){
            $this->error('Stánka nebyla nalezena');
        }
        return $post;
    }

    public function getArticleOrderPoradi()
    {
        return $this->database->table('article')->order('poradi');
    }

    public function getArticlesUrl($url,$articlePoradi)
    {
        $array = $articlePoradi;
        if(!$array){ return false; }
        foreach($array as $str){ if($str->url === $url){ $stranka = array($str);} }
        if(!isset($stranka)){  return false;}else{ return $stranka;  }
    }

/** Logo a footer */
    public function footer()
    {
        return $this->database->table('footer')->get(1);
    }
    public function updateFooter($values)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('footer')->where('id = ?', 1)->update([
                "content" => $values,
            ]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function logo($id,$thems)
    {
        if ($thems == ' ') {
            $this->database->table('logo')->where('id', $id)->update(['thems' => null]);
        } else {
            $this->database->table('logo')->where('id', $id)->update(['thems' => $thems]);
        }
    }
    public function getLogo()
    {
        return $this->database->table('logo')->get(1);
    }
    public function getLogoFooter()
    {
        return $this->database->fetchAll('SELECT name,thems,img,content FROM logo, footer WHERE footer.id = logo.id');
    }

/** post */
    public function searchPostWhisperer($hledat)
    {
        return $this->database->query('SELECT title,content,url FROM posts WHERE MATCH(title,content) AGAINST (? IN NATURAL LANGUAGE MODE) LIMIT 5', $hledat)->fetchAll();
    }
    public function findPublishedArticles(): Nette\Database\Table\Selection
	{
		return $this->database->table('posts')
			->where('created_at < ', new \DateTime)
			->order('created_at DESC');
	}
    public function getRecArticles()
    {
        return $this->database->table('posts')->where('recommended','ano')->order('id DESC');
    }
    /** aktivace */
    public function check($email,$active_key)
    {
        return $this->database->table('users')->where('email = ? AND active_key = ?' , $email, $active_key)->fetch();
    }
    public function checkTrue($email,$active_key,$id)
    {
        FileSystem::createDir('../www/users/'.$id);
        return $this->database->table('users')->where('email = ? AND active_key = ?' , $email, $active_key)->update([ 'active_key' => null]);
    }
    /** komentare */
    public function comments()
    {
        return $this->database->query('SELECT com.*, com.id AS idecko, com.created_at AS datum, p.title AS title FROM comments AS com JOIN posts AS p ON p.id = post_id');
    }


}