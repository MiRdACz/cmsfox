<?php
declare(strict_types=1);
namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Utils\Strings;
use Nette\Application\UI\Form;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use App\Services\FactoryFilterMonth;
use App\Services\FactoryFilterSnippetContent;
use App\Services\FactoryShortCode;
use App\Services\FormLogin;
use App\Model\PageModel;
use App\Model\EmailModel;
use App\Model\ArticleModel;
use App\Model\ShortCodeModel;

final class HomepagePresenter extends Nette\Application\UI\Presenter
{
    /** @var factoryFilterMonth @inject */
    protected $factoryFilterMonth;
    /** @var FactoryFilterSnippetContent @inject */
    protected $factoryFilterSnippetContent;
    /** @var ShortCodeModel @inject */
    private $shortCodeModel;
    /** @var FactoryShortCode @inject */
    public $factoryShortCode;
    /** @var Nette\Http\Response */
    private $http;
    /** @var FormLogin @inject */
    public $formFactory;

    private $articleModel;
    private $pageModel;
    private $emailModel;

    public function __construct(PageModel $pageModel,ShortCodeModel $shortCodeModel,EmailModel $emailModel,ArticleModel $articleModel,Nette\Http\Response $http)
    {
        $this->pageModel = $pageModel;
        $this->shortCodeModel = $shortCodeModel;
        $this->emailModel = $emailModel;
        $this->articleModel = $articleModel;
        $this->http = $http;
    }
    protected function beforeRender()
    {
        /** Filter pro ceske mesice a utrzek z textu */
       $this->template->addFilter('czechMonth', new FactoryFilterMonth());
       $this->template->addFilter('snippetContent', new FactoryFilterSnippetContent());
       /** menu */
       $this->template->url = $this->getParameter('url');
    }
    protected function shortCode($content,$shortcode)
    {
        if($content === null){return false;}
        if (Strings::contains($content, '[[kontakt]]') === true)
        {
            foreach($shortcode as $index=>$item) {
                 $parametrsContact = array('title_email' => $item->contact_title_email, 'content' => $item->contact_content, 'title' => $item->contact_title, 'sendbtn' => $item->contact_send, 'webtime' => time());
                 $content = Strings::replace($content, "~\[\[kontakt\]\]~", $this->template->renderToString(dirname(__DIR__) . '/Services/contact.latte', $parametrsContact));
            }
        }
        if (Strings::contains($content, '[[formular]]') === true)
        {
            $formInput = $this->shortCodeModel->getShortFormInput();
            foreach($shortcode as $index=>$item) {
                $parametrsForm = array('inputs' => $formInput,'form_title' => $item->form_title);
                $content = Strings::replace($content, "~\[\[formular\]\]~", $this->template->renderToString(dirname(__DIR__) . '/Services/form.latte', $parametrsForm));
            }
        }
        return $content;
    }
    public function renderDefault(string $url='')
    {
        /** Stranka pro prvni zobrazeni, zakladne url je prazdne prvni stranka */
        $pageOrder = $this->pageModel->pageOrderSort();
        /** menu */
        $this->template->menus = $pageOrder;
        /** vytahneme si obsah podle url */
        $page = $this->pageModel->getPageFromUrl($url, $pageOrder);
        if($page === false){$this->error('Stránka nebyla nalezena');}
        /** shortCode */
        $shortCodeDb = $this->shortCodeModel->getShortCode();
        $shortCode = $this->factoryShortCode->shortCode($page[0]['content'],$url,$shortCodeDb);
        $shortCode = $this::shortCode($shortCode,$this->shortCodeModel->getShortCodeOther());
        /** Logo a footer */
        $logoFooter = $this->pageModel->getLogoFooter();
        $this->template->footer_content = $this->factoryShortCode->shortCode($logoFooter->footer_content,null,$shortCodeDb);
        $this->template->logo = $logoFooter;
        /** pages */
        $this->template->page = $shortCode;
        $this->template->pageTitle = $page[0]['title'];
        $this->template->pageDescription = $page[0]['description'];
        /** Blog */
        if($url === 'blog'){
            $page = intval($this->getParameter('stranka'));
            if(!$page){ $page =1; }
            // Vytáhneme si publikované články
            $posts = $this->articleModel->findPublishedArticles();
            // vytahne vsechny clanky pro doporucene
            $this->template->postsRecomended = $this->articleModel->RecomendedArticles();
            // a do šablony pošleme pouze jejich část omezenou podle výpočtu metody page
            $lastPage = 0;
            $this->template->posts = $posts->page($page, 5, $lastPage);
            // a také potřebná data pro zobrazení možností stránkování
            $this->template->pageFirst = $page;
            $this->template->lastPage = $lastPage;
        }
    }
    public function menuFooterLogo()
    {
        /** Stranka pro prvni zobrazeni, zakladne url je prazdne prvni stranka */
        $pageOrder = $this->pageModel->pageOrderSort();
        /** menu */
        $this->template->menus = $pageOrder;
        /** Logo a footer */
        $shortCodeDb = $this->shortCodeModel->getShortCode();
        $logoFooter = $this->pageModel->getLogoFooter();
        $this->template->footer_content = $this->factoryShortCode->shortCode($logoFooter->footer_content,null,$shortCodeDb);
        $this->template->logo = $logoFooter;
    }
    /** aktivace */
    public function renderActive(){

        /** aktive get parametrs */
        $email = $this->getParameter('email');
        $active_key = $this->getParameter('key');
        if(!$email || !$active_key){
            $this->redirect('Homepage:default');exit;
        }
        $checkActive = $this->pageModel->check($email,$active_key);
        if($checkActive){
            $this->pageModel->checkTrue($email,$active_key,$checkActive['id']);
            $this->flashMessage("Váš účet je aktivován!", 'alert-success');
            $this->redirect('sign');exit;
        }
        else{
            $this->flashMessage("Chyba! Kontaktujte administrátora", 'warning-success');
            $this->redirect('Homepage:default');exit;
        }
    }
    /** registrace */
    public function renderRegistration()
    {
        $this::menuFooterLogo();
        $httpRequest = $this->getHttpRequest();
        $ip = $httpRequest->getRemoteAddress();
        $this->template->block = $this->articleModel->block($ip);
        $this->template->ip = $ip;
        
    }
    /** Prihlaseni */
    public function renderSign(){
        $this::menuFooterLogo();
        $httpRequest = $this->getHttpRequest();
        $ip = $httpRequest->getRemoteAddress();
        $this->template->block = $this->articleModel->block($ip);
        $this->template->ip = $ip;
    }
    /** Akce Odhlaseni */
    public function actionOut(): void
    {
        if (!$this->getUser()->isLoggedIn()) { $this->redirect('Homepage:sign');exit; }
        $this->getUser()->logout(true);
        $this->flashMessage('Odhlášení bylo úspěšné.','alert-success');
        $this->redirect('Homepage:');exit;
    }
    /** Obnova hesla */
    public function renderLostpassword(){
        $this::menuFooterLogo();
    $httpRequest = $this->getHttpRequest();
    $ip = $httpRequest->getRemoteAddress();
    $this->template->block = $this->articleModel->block($ip);
    $this->template->ip = $ip;
    }
    /* Formulare */
    /* Prihlaseni */
    protected function createComponentSignForm()
    {
        $form = $this->formFactory->createSignForm();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->redirect('this');exit;
        };
        return $form;
    }
    /* Konec prihlaseni */
    /* Registrace form*/
    protected function createComponentRegForm()
    {
        $form = $this->formFactory->createRegForm();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->flashMessage('Děkuji za registraci, byl Vám zaslán aktivační email!', 'alert-success');
            $this->redirect('this');exit;
        };
        return $form;
    }
    protected function createComponentRegFormAdmin()
    {
        $form = $this->formFactory->createRegFormAdmin();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->flashMessage('Děkuji za registraci, byl Vám zaslán aktivační email!', 'alert-success');
            $this->redirect('this');exit;
        };
        return $form;
    }
    /* Konec registrace form*/
    /* Obnova hesla */
    protected function createComponentLostForm()
    {
        $form = $this->formFactory->lostForm();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->flashMessage('Obnova hesla proběhla, byl Vám zaslán email s novým heslem!', 'alert-success');
            $this->redirect('Homepage:default');exit;
        };
        return $form;
    }
    /* Konec obnova hesla */
    
    /* Formular shortcode */
    protected function createComponentFormForm(): Form
    {
        $form = new UI\Form;
        $form->addProtection('Vypršel časový limit, odešlete formulář znovu');
        $inputs = $this->shortCodeModel->getShortCodeOther();
        foreach($inputs as $input){
            foreach ($input->related('form_input') as $formInput) {
                $form->addText($formInput->input,$formInput->label);
            }
        }
        $form->addSubmit('send', 'Odeslat');

        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $shortcode = $this->shortCodeModel->getShortCodeOther();
            $this->emailModel->messageTo($shortcode[1]->form_email, 'MiRdAFoX');
            $this->emailModel->messageSubject('Formulář z webu '.$shortcode[1]->form_title);
            $content = '';
            foreach($values as $index => $value){
                foreach($shortcode as $valueShorCode){
                    foreach($valueShorCode->related('form_input') as $label){
                        $content .= '<p>'.$label->label.' - '.$value.'</p>';
                    }
                }
            }
            $this->emailModel->messageContent('<h1>'.$shortcode[1]->form_title.'</h1>'.$content);
            $this->emailModel->sendEmail();
            $this->flashMessage("Formulář byl odeslán", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    /* Konec formular shortcode */


}