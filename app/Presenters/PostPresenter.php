<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use App\Model\ArticleManager;
use App\Services\CategoryForm;
use App\Services\FactoryFilter;
use App\Model\PostManager;
use App\Services\FactoryShortCode;


final class PostPresenter extends Nette\Application\UI\Presenter
{

    private $articleManager;
    /** @var CategoryForm @inject */
    protected $categoryFormFactory;
    /** @var FactoryFilter @inject */
    protected $factoryFilter;
    protected $postManager;
    /** @var FactoryShortCode @inject */
    public $factoryShortCode;

    public function __construct(ArticleManager $articleManager, PostManager $postManager)
    {
        $this->articleManager = $articleManager;
        $this->postManager = $postManager;
    }

    protected function createComponentCategoryForm()
    {
        $form = $this->categoryFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->redirect('this');
            exit;
        };

        return $form;
    }

    protected function createComponentCommentForm(): Form
    {
        $form = new Form; // means Nette\Application\UI\Form

        $form->addProtection('Vypršel časový limit, odešlete formulář znovu');

        $form->addText('name', 'Jméno:')
            ->setRequired('Zapoměli jste vyplnit jméno');

        $form->addEmail('email', 'Email:');

        $form->addTextArea('content', 'Komentář:');

        $form->addSubmit('send', 'Publikovat komentář');

        //$form->getElementPrototype()->onsubmit('tinyMCE.triggerSave()');

        $form->onSuccess[] = [$this, 'commentFormSucceeded'];

        return $form;
    }

    public function commentFormSucceeded(Form $form, \stdClass $values): void
    {
        $httpRequest = $this->getHttpRequest();

        $ip = $httpRequest->getRemoteAddress();

        $kontrola = $this->postManager->commentBlock();
        foreach($kontrola as $k){
            if($k['ip'] = $ip ){
                $this->flashMessage("Vaše IP adresa  je blokována", 'alert-danger');
                $this->redirect('this');
                exit;
            }else{continue;}
        }
        $getPostId = $this->postManager->getUrlFromDatabasePost($this->getParameter('url'));
        $postId = $getPostId->id;
        $validace = preg_match("/<script/", $values->content);
        $nepovoleneZnaky = preg_match("/(document+\\.)|(\\')/",$values->content);
        if($validace == 1 || $nepovoleneZnaky == 1){
            $values->content = trim($values->content);
            $values->content = stripslashes($values->content);
            $values->content = htmlspecialchars($values->content);
            $values->content;
        }else{
            $values->content;
        }

        $user = $this->getUser();
        if($user->getIdentity() == null){$idUser = null;}else{$idUser = $user->getIdentity()->id;}


        $this->postManager->commentAdd($ip,$values,$postId,$idUser);
        $this->flashMessage('Děkuji za komentář', 'alert-success');
        $this->redirect('this');
        exit;
    }


    protected function beforeRender()
    {
        $this->template->addFilter('ceskyMesic', new FactoryFilter());
        /** Logo a footer */
        $this->template->logoFooter = $this->articleManager->getLogoFooter();
        /** menu */
        $this->template->url = 'blog';
        $this->template->parrent = $this->articleManager->getArticleOrderPoradi();
    }
    public function renderShow(string $url) :void
    {
        $post = $this->articleManager->getPostArticlesUrl($url);
        $shortCode = $this->factoryShortCode->shortCodeGalerie(array($post->content));
        $shortCode = $this->factoryShortCode->shortCodeSlider($shortCode);
        $this->template->post = $shortCode;
        $this->template->posttitle = $post->title;
        $this->template->postcreated = $post->created_at;
        $this->template->comments = $post->related('comment')->order('created_at');
    }
}