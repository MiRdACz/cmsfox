<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use App\Model\ArticleManager;
use App\Model\EmailModel;
use App\Services\SignForm;
use App\Services\RegForm;
use App\Services\LostForm;
use App\Services\FactoryFilter;
use App\Services\FactoryFilterUkazka;
use App\Services\FactoryShortCode;
use App\Services\FormularForm;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;


final class HomepagePresenter extends Nette\Application\UI\Presenter
{

    private $emailModel;
    private $articleManager;
    /** @var LostForm @inject */
    public $lostFormFactory;
    /** @var RegForm @inject */
    public $regFormFactory;
    /** @var SignForm @inject */
    public $signFormFactory;
    /** @var FactoryFilter @inject */
    protected $factoryFilter;
    /** @var FactoryFilterUkazka @inject */
    protected $factoryFilterUkazka;
    /** @var FactoryShortCode @inject */
    public $factoryShortCode;
    /** @var FormularForm @inject */
    public $formularFormFactory;

    public function __construct(ArticleManager $articleManager, EmailModel $emailModel)
    {
        $this->articleManager = $articleManager;
        $this->emailModel = $emailModel;
    }
    protected function createComponentFormularForm(): Form
    {
        $form = $this->formularFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $shortcode = $this->emailModel->formularEmail();
            $this->emailModel->messageTo($shortcode->email, 'MiRdAFoX');
            $this->emailModel->messageSubject('Formulář z webu '.$shortcode->title);
            $obsah = '';
            foreach($values as $index => $value){
                $lab = $this->articleManager->getShortFormularLabel($index);
                $obsah .= '<p>'.$lab->label.' - '.$value.'</p>';
            }
            $this->emailModel->messageContent('<h1>'.$shortcode->title.'</h1>'.$obsah);
            $this->emailModel->sendEmail();
            $this->flashMessage("Formulář byl odeslán", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentSignForm()
    {
        $form = $this->signFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentRegForm()
    {
        $form = $this->regFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form) {
            $this->flashMessage('Děkuji za registraci, byl Vám zaslán aktivační email!', 'alert-success');
            $this->redirect('this');exit;
        };
        return $form;
    }
    protected function createComponentLostForm()
    {
        $form = $this->lostFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form) {
                $this->flashMessage('Obnova hesla proběhla, byl Vám zaslán email s novým heslem!', 'alert-success');
                $this->redirect('Homepage:default');exit;
        };
        return $form;
    }

    protected function createComponentContactForm(): Form
    {
        $mySection = $this->getSession('komentar');

        $form = new Form;
        $form->addProtection('Vypršel časový limit, odešlete formulář znovu');
        $form->addEmail('email', 'Email:')
            ->setDefaultValue($mySection->email)
            ->setRequired('Zapoměli jste vyplnit email');
        $form->addTextArea('content', 'Komentář:')
            ->setDefaultValue($mySection->content)
            ->setRequired('Zapoměli jste vyplnit zprávu');
        $form->addTextArea('antispam', 'Antispam:');
        $form->addHidden('website');
        $form->addSubmit('send', 'Odelast zprávu');
        $form->onValidate[] = [$this, 'validateContactFormForm'];
        $form->onSuccess[] = [$this, 'contactFormSucceeded'];
        return $form;
    }
    public function validateContactFormForm(Form $form): void
    {
        $values = $form->getValues();
        $antispam = $values->antispam;
        $mySection = $this->getSession('komentar');
        $mySection->email = $values->email;
        $mySection->setExpiration('5 seconds');
        if($antispam !== date("Y")){
            $mySection->content = $values->content;
            $this->flashMessage('Chyba ověření, jste robot?', 'alert-danger');
            $this->redirect('this');
            exit;
        }
        $time = time()-2;
        if($values->website >= $time ){
            $this->flashMessage('Chyba ověření, jste robot?', 'alert-danger');
            $this->redirect('this');
            exit;}
    }
    public function contactFormSucceeded(Form $form, \stdClass $values): void
    {
        $mySection = $this->getSession('komentar');
        $mySection->remove();
        $shortcode = $this->emailModel->kontaktEmail();
        $this->emailModel->messageTo($shortcode->email, 'MiRdAFoX');
        $this->emailModel->messageSubject('Kontaktní formulář z webu');
        $this->emailModel->messageContent('<p>Zpráva od '.$values->email.'</p><p>'.$values->content.'</p>');
        $this->emailModel->sendEmail();

       $this->flashMessage('Děkuji za zprávu!', 'alert-success');
       $this->redirect('this');
       exit;
    }
     public function shortCodeKontakt($array)
    {
        $shortcode = '[[kontakt]]';
        $result = $array;
        if(strstr( $result[0], $shortcode ) == true){
            $data = $this->articleManager->getShortKontakt();

            $par = array('popisEmail' => $data->titleemail, 'content' => $data->content,'title' => $data->title,'sendbtn' => $data->sendbtn,'webtime' => time());
            $result = array(str_replace($shortcode,$this->template->renderToString(__DIR__ . '/kontakt.latte',$par),$result[0]));
        }else{
            $result = array($result[0]);
        }
        return $result;
    }
    public function shortCodeFormular($array)
    {
        $shortcode = '[[formular]]';
        $result = $array;
        if(strstr( $result[0], $shortcode ) == true){
            $data = $this->articleManager->getShortFormularInput();
            $dataTitle = $this->articleManager->getShortFormular();
            $par = array('items' => $data,'formular' => $dataTitle);
            $result = array(str_replace($shortcode,$this->template->renderToString(__DIR__ . '/formular.latte',$par),$result[0]));
        }else{
            $result = array($result[0]);
        }
        return $result;
    }
    /** Akce Odhlaseni */
    public function actionOut(): void
    {
    if (!$this->getUser()->isLoggedIn()) { $this->redirect('Homepage:sign');exit; }
    $this->getUser()->logout(true);
    $this->flashMessage('Odhlášení bylo úspěšné.','alert-success');
    $this->redirect('Homepage:');exit;
    }

    protected function beforeRender()
    {
        $this->template->addFilter('ceskyMesic', new FactoryFilter());
        $this->template->addFilter('ukazka', new FactoryFilterUkazka());
        /** Logo a footer */
        $this->template->logoFooter = $this->articleManager->getLogoFooter();
        /** menu */
        $this->template->url = $this->getParameter('url');
    }

    private function menu()
    {
        /** menu */
        $articlePoradi = $this->articleManager->getArticleOrderPoradi();
        $this->template->parrent = $articlePoradi;
        /** Logo a footer */
        $this->template->logoFooter = $this->articleManager->getLogoFooter();
    }
    public function renderDefault(string $url='')
    {	
        /** Stranka pro zobrazeni, zakladne je url prazdne prvni stranka */
        $articlePoradi = $this->articleManager->getArticleOrderPoradi();
        $stranka = $this->articleManager->getArticlesUrl($url, $articlePoradi);
        if($stranka === false){$this->error('Stránka nebyla nalezena');}
        /** galerie short code */
        $shortCode = $this->factoryShortCode->shortCodeGalerie(array($stranka[0]['content']));
        /** slider short code */
        $shortCode = $this->factoryShortCode->shortCodeSlider($shortCode);
        /** kontakt short code */
        /* $shortCode = $this->factoryShortCode->shortCodeKontakt($shortCode); */
        $shortCode = $this::shortCodeKontakt($shortCode);
        /* short formular **/
        $shortCode = $this::shortCodeFormular($shortCode);
        /** vypis short code */
        $shortCode = $this->factoryShortCode->shortCodeVypis($shortCode,$url);

        /** menu */
        $this->template->parrent = $articlePoradi;

        /** pages */
        $this->template->pages = $shortCode;
        $this->template->pageTitle = $stranka[0]['title'];
        $this->template->pageDescription = $stranka[0]['description'];

        /** Blog */
        if($url === 'blog'){
            $page = intval($this->getParameter('stranka'));
            if(!$page){
                $page =1;
            }
            // Vytáhneme si publikované články
            $posts = $this->articleManager->findPublishedArticles();
            // vytahne vsechny clanky pro doporucene
            $this->template->postsRec = $this->articleManager->getRecArticles();
            // a do šablony pošleme pouze jejich část omezenou podle výpočtu metody page
            $lastPage = 0;
            $this->template->posts = $posts->page($page, 5, $lastPage);
            // a také potřebná data pro zobrazení možností stránkování
            $this->template->pageFirst = $page;
            $this->template->lastPage = $lastPage;
        }

    }
    /** Prihlaseni */
    public function renderSign()
    {
        $this::menu();
    }
    /** registrace */
    public function renderRegistration()
    {
        $this::menu();
        $check = $this->getParameter('heslo');
        if($check == 'uvm'){
            $this->template->admin = "ok";
        }else{
            $this->template->admin = "not";
        }
    }
    /** aktivace */
     public function renderActive(){

        /** aktive get parametrs */
        $email = $this->getParameter('email');
        $active_key = $this->getParameter('key');

        if(!$email || !$active_key){
            $this->redirect('Homepage:default');
            exit;
        }
        $check = $this->articleManager->check($email,$active_key);
        if($check){
            $this->articleManager->checkTrue($email,$active_key,$check['id']);
            $this->flashMessage("Váš účet je aktivován!", 'alert-success');
            $this->redirect('sign');exit;
        }
        else{
            $this->flashMessage("Chyba! Kontaktujte administrátora", 'warning-success');
            $this->redirect('Homepage:default');
            exit;
        }
    }
/** obnova hesla */
    public function renderLostpassword(){
    $this::menu();
    }


    public function handleNaseptavac()
    {
        $hledat = $this->getParameter('value');
        if ($this->isAjax()) {
            if(strlen($hledat) <= 2 && strlen($hledat) != 0){ exit; }
            if(strlen($hledat) == 0){
                $result = false;
            }else{
                $result = $this->articleManager->searchPostWhisperer($hledat);
                if(empty($result)){ $result = false; }
            }
            $this->template->naseptavac = $result;
            $this->redrawControl('naseptavac');
        }
    }

}