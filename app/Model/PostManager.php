<?php
namespace App\Model;

use Nette;
use Nette\Database\Table\Selection;


class PostManager
{
    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;


    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }
    public function getIdPost($id)
    {
       return $this->database->table('posts')->get($id);
    }
    public function newBlog($values)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('posts')->insert([
                'url' => $values->url,
                'content' => $values->content,
                'title' => $values->title,
                'img' => $values->img,
                'recommended' => null,
                'author' => $values->author,
            ]);$this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function editBlog($values,$postId)
    {
        $this->database->beginTransaction();
        try {
        $post = $this->database->table('posts')->get($postId);
        $post->update([
            'url' => $values->url,
            'content' => $values->content,
            'title' => $values->title,
            'img' => $values->img,
        ]);$this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function recommend($id)
    {
        return $this->database->table('posts')->get($id);
    }
    public function recommendUp($rec,$id)
    {
        $this->database->beginTransaction();
        try {
        $this->database->query('UPDATE posts SET', [
            'recommended' => $rec,
        ], 'WHERE id = ?', $id);
        $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function deleteRecommennd($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('comments')->where('post_id', $id)->delete();
            $this->database->query('DELETE FROM posts WHERE id = ?', $id);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function check($url,$id)
    {
       return $this->database->table('posts')->where('url = ? AND id != ?', $url, $id)->fetch();
    }
    public function checkUrl($url)
    {
       return $this->database->table('posts')->where('url = ?', $url)->fetch();
    }
    public function commentBlock()
    {
        return $this->database->query('SELECT * FROM commentsblock');
    }
    public function commentAdd($ip,$values,$postId,$idUser)
    {
        $user = $this->database->table('users')->get($idUser);
        $name = $values->name;
        $avatar = null;
        if($user){ $name = $user->username; $avatar=$user->avatar;}

        $this->database->beginTransaction();
        try {
            $this->database->table('comments')->insert([
            'post_id' => $postId,
            'name' => $name,
            'email' => $values->email,
            'content' => $values->content,
            'avatar' => $avatar,
            'ip' => $ip,
        ]);
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function getUrlFromDatabasePost($url){
        return $this->database->table('posts')->where('url', $url)->fetch();
    }

    public function deleteComment($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('comments')->where(['id' => $id])->delete();
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }

    public function deleteBlock($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('commentsblock')->where(['id' => $id])->delete();
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }
    public function comments($id)
    {
        return $this->database->table('comments')->get($id);
    }
    public function commentsblock()
    {
        return $this->database->table('commentsblock');
    }

    public function blockId($id)
    {
        $this->database->beginTransaction();
        try {
            $ip = $this->database->table('comments')->where(['id' => $id])->fetch();
            $kontrola = $this->database->table('commentsblock')->where(['ip' => $ip['ip']]);
            if (count($kontrola) > 0) {
                $result = false;
            } else {
                $result = true;
                $this->database->table('commentsblock')->insert(['ip' => $ip['ip']]);
            }
            $this->database->commit();
            return $result;
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }

    public function findPublishedComment(): Nette\Database\Table\Selection
	{
		return $this->database->table('comments')
			->order('created_at DESC');
	}
}