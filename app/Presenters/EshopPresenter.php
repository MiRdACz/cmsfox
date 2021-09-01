<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use App\Model\ArticleManager;
use App\Model\EmailModel;

use App\Model\EshopModel;



use App\Services\SignForm;
use App\Services\RegForm;
use App\Services\LostForm;
use App\Services\FactoryFilter;
use App\Services\FactoryFilterUkazka;
use App\Services\FactoryShortCode;
use App\Services\FormularForm;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Http\Request;
use Nette\Http\Url;
use Nette\Http\UrlScript;


final class EshopPresenter extends Nette\Application\UI\Presenter
{

    private $emailModel;
    private $eshopModel;

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

    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(ArticleManager $articleManager, EmailModel $emailModel, EshopModel $eshopModel,Nette\Database\Context $database)
    {
        $this->articleManager = $articleManager;
        $this->emailModel = $emailModel;
        $this->eshopModel = $eshopModel;
        $this->database = $database;
    }
    protected function createComponentKosikForm(): Form
	{
        $form = new Form;
        $form->addProtection('Vypršel časový limit, odešlete formulář znovu');
        $form->addCheckbox('agree', 'Souhlasím s podmínkami')
            ->setRequired('Je potřeba souhlasit s podmínkami');
        $transport = $this->eshopModel->getTransport();
        $trans=[];
        foreach($transport as $index => $value){
            $trans += [$value['id'] => $value['name']];
        }
        //$trans += ['0' => 'Zásilkovna'];
        $form->addRadioList('transport','Doprava:', $trans)
            ->setRequired('Prosím vyplňte dopravu.');

    $form->addText('zasilkovna', 'Zásilkovna:');
    $payment = $this->eshopModel->getPayment();
    $pay=[];
    foreach($payment as $index => $value){
        $pay += [$value['id'] => $value['name']];
    }

    $form->addRadioList('payment','Platba:', $pay)
        ->setRequired('Prosím vyplňte platbu.');


    $form->addText('jmeno', 'Jméno:')
        ->setRequired('Prosím vyplňte Vaše jméno.');

    $form->addText('prijmeni', 'Příjemní')
        ->setRequired('Prosím vyplňte Vaše příjemní.');

    $form->addText('ulice', 'Ulice:')
        ->setRequired('Prosím vyplňte ulici.');

    $form->addText('mesto', 'Město:')
        ->setRequired('Prosím vyplňte město.');

    $form->addText('psc', 'PSČ:')
        ->setRequired('Prosím vyplňte PSČ.');

    $form->addText('telefon', 'Váš telefon:')
        ->setRequired('Prosím vyplňte Váš telefon.');


    $form->addEmail('email', 'Váš email:')
        ->setRequired('Prosím vyplňte email');

    $form->addText('poznamka', 'Poznámka:');

    $form->addSubmit('send', 'Objednat');

    $form->onValidate[] = [$this, 'kosikValiding'];

    $form->onSuccess[] = [$this, 'kosikSucceeded'];

    return $form;
    }
    public function kosikValiding(Form $form)
    {
        $values = $form->getValues();
        $jmeno = $values->jmeno;
        $prijmeni = $values->prijmeni;
        $email = $values->email;
        $psc = $values->psc;
        $tel = $values->telefon;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form['email']->addError('Zadejte správně emailovou adresu.');
        }

        if (!preg_match("/^[a-zA-Z ]*$/",$jmeno)) {
            $form['jmeno']->addError('Zadejte pouze slova s mezerou.');
        }
        if (!preg_match("/^[a-zA-Z ]*$/",$prijmeni)) {
            $form['prijmeni']->addError('Zadejte pouze slova s mezerou.');
        }
        if (!preg_match("/^[0-9 ]*$/",$psc)) {
            $form['psc']->addError('Zadejte pouze číslice s mezerou.');
        }
        if (!preg_match("/^[0-9 ]*$/",$psc)) {
            $form['psc']->addError('Zadejte pouze číslice s mezerou.');
        }
        if (!preg_match("/^(\\++[0-9]{3})?[1-9][0-9]{8}+$/",$tel)) {
            $form['telefon']->addError('Zadejte číslice bez mezer, je povolena předvolba např. +420.');
        }

    }
    public function kosikSucceeded(Form $form, \stdClass $values): void
    {

        /** @var  $httpRequest url pro email */
        $httpRequest = $this->getHttpRequest();
        $uri = $httpRequest->getUrl();
        $url = $uri->host;


            $zbozi = $this->getSession('kosik');
            $cena_celkem = 0;
            foreach($zbozi as $index => $value){
                $cena_celkem += round($value['price']*$value['pocet']*(1+$value['dph']/100),0);
            }
            if($values->transport == 0){
                $doprava = 'Zásilkovna: '.$values->zasilkovna;
                $cenaDoprava = $this->eshopModel->getTransportSelect($values->transport)->price;

            }else{
                $doprava = $this->eshopModel->getTransportSelect($values->transport)->name;
                $cenaDoprava = $this->eshopModel->getTransportSelect($values->transport)->price;
            }
            // AKCE bude tady i poznamka
            $akceCena = $this->eshopModel->akce();
            $akce = null;
            if($akceCena->cena <= $cena_celkem){$akce = $akceCena->akce;}
            $obj = $this->eshopModel->objednavka($values,$doprava,$cena_celkem,$akce);
            $id = $obj;
            $html = "<p style='text-align: center;'><b>OBJEDNÁVKA ZBOŽÍ č.$id</b></p><br>";
            $html .= '<tr><td style="width: 70%!important;"><b>DORUČOVACÍ ÚDAJE</b><br>'.'Jméno: '.$values->jmeno.' '.$values->prijmeni.'<br>Ulice: '.$values->ulice.'<br>Město: '.$values->mesto.'<br>Doprava: '.$doprava.'<br>Telefon: '.$values->telefon.'</td>';
            $html .= '<td style="vertical-align: top"><b>OBJEDNANÉ ZBOŽÍ</b><br>';

            foreach($zbozi as $index => $value){

                $this->eshopModel->objednavkaProdukt($value,$id);

                $html .= $value['title'].' cena: '.$value['price'].' počet: '.$value['pocet'].'ks <br>';

                $sklad = $this->template->test = $this->eshopModel->skladObj($value);
                $pocetO = number_format($value['pocet']);
                foreach($sklad as $sk){
                    $pocetS = number_format($sk['sklad']);
                }

                $pocet = ($pocetS - $pocetO);
                if($pocet > -1){
                    $this->database->table('sklad')->where('product_id',$value['id'])->update([
                        'sklad' => $pocet,
                    ]);
                }else{
                    $this->flashMessage('Není skladem', 'danger');
                    $this->redirect('Eshop:default');exit;
                }

            }

            $html .= '</td></tr>';
            foreach ($zbozi as $index => $value) {
                unset($zbozi["$index"]);
            }

            $this->database->commit();

            $this->emailModel->messageTo($values->email);
            $this->emailModel->messageSubject('Objednávka zboží od MiRdAFoX č.'.$id);
            $this->emailModel->messageContent($html.'<p>Děkujeme</p>');
            $this->emailModel->sendEmail();

            $this->flashMessage('Děkuji za objednávku, byl Vám zaslán email s přehledem objednávky!', 'alert-success');

        $this->redirect('Eshop:default');exit;
    }
    protected function beforeRender()
    {
        /** Logo a footer */
        $this->template->logoFooter = $this->articleManager->getLogoFooter();
    }
    public function renderDefault($id, $razeni, $limit, $poradi)
    {
        $url = 'eshop';
        $this->template->url = $url;
        /** menu */
        $articlePoradi = $this->articleManager->getArticleOrderPoradi();
        $this->template->parrent = $articlePoradi;
        $stranka = $this->articleManager->getArticlesUrl($url, $articlePoradi);
        $this->template->pages = array($stranka[0]['content']);
        $this->template->pageTitle = $stranka[0]['title'];
        $this->template->pageDescription = $stranka[0]['description'];

        $httpRequest = $this->getHttpRequest();
        $httpResponse = $this->getHttpResponse();

        $limit = intval($this->getParameter('limit'));
        $razeni = $this->getParameter('razeni');
        $poradi = $this->getParameter('poradi');

        $cookieCategory = $httpRequest->getCookie('cookieCategory');

        if($limit == null){$limit=4;}
        if($cookieCategory == null){ $httpResponse->setCookie('cookieCategory', '', '1 days');}
        if($razeni == null){ $razeni ='id';}

        $category = $httpRequest->getUrl();
        $slugUrl = $category->getPath();
        $slugs = explode('/', $slugUrl);
        $lastSlug = end($slugs);
        $check = $this->eshopModel->categorSlug($lastSlug);
        foreach($check as $value){
            $cookieCategory = $value->id;
        }

        /** aktivni session */

        $this->template->limit = $limit;
        $this->template->category = $cookieCategory;
        $this->template->razeni = $razeni;
        $this->template->poradi = $poradi;

        /** kategorie */
        $this->template->query = $this->eshopModel->query();
        $this->template->testParent = $this->eshopModel;

        /** Kosik */
        $mySection = $this->getSession('kosik');
        $mySectionCheck = $this->getSession();
        if($mySectionCheck->hasSection('kosik')){
            $mySectionCheck = 'ano';
        }else{$mySectionCheck = 'ne';}
        $this->template->mySectionCheck = $mySectionCheck;
        $this->template->kosik = $mySection;

        $radky = $this->eshopModel->findArticleEshopId($razeni,boolval($poradi),(int)$limit, $cookieCategory);
        $this->template->ajaxContent = $radky;

    }
    public function renderKosik()
    {
        $url = 'eshop';
        $this->template->url = $url;
        /** menu */
        $articlePoradi = $this->articleManager->getArticleOrderPoradi();
        $this->template->parrent = $articlePoradi;
        /** Kosik */
        $mySection = $this->getSession('kosik');
        $mySectionCheck = $this->getSession();
        if($mySectionCheck->hasSection('kosik')){
            $mySectionCheck = 'ano';
        }else{$mySectionCheck = 'ne';}
        $this->template->mySectionCheck = $mySectionCheck;
        $this->template->kosik = $mySection;
        /** doprava */
        $this->template->transport = $this->eshopModel->getTransport();
        $this->template->apiKey = $this->eshopModel->transNull();
        /** Platba */
        $payment = $this->eshopModel->getPayment();
        $this->template->payment = $payment;
    }
    public function renderShow($url){

        $urli = 'eshop';
        $this->template->url = $urli;
        /** menu */
        $articlePoradi = $this->articleManager->getArticleOrderPoradi();
        $this->template->parrent = $articlePoradi;
        /** refferer */
        $httpRequest = $this->getHttpRequest();
        $this->template->refferer = $httpRequest->getReferer();

        $post = $this->eshopModel->getUrlFromDatabaseProduct($url);
        if (!$post) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->post = $post;

    }
    public function handleNaseptavac()
    {
        $hledat = $this->getParameter('value');
        if ($this->isAjax()) {
            if(strlen($hledat) <= 2 && strlen($hledat) != 0){ exit; }
            if(strlen($hledat) == 0){
                $result = false;
            }else{
                $result = $this->eshopModel->searchProductWhisperer($hledat);
                if(empty($result)){ $result = false; }
            }
            $this->template->naseptavac = $result;
            $this->redrawControl('naseptavac');
        }
    }
    public function handleKosik($id)
    {
        $id = $this->getParameter('idcko');
        $sklad = $this->eshopModel->skladId($id);
        if (!$sklad) {
            $this->redirect('this');
            exit;
        }

        if ($this->isAjax()) {

            $kosik = $this->getSession('kosik');
            $pocetSklad = $sklad['sklad'];

            if ($kosik[$id]) {
                if ($pocetSklad <= $kosik[$id]->pocet++) {
                    $kosik[$id]->pocet = $pocetSklad;
                } else {
                    $kosik[$id]->pocet;
                }

            } else {
                $kosik[$id] = $this->eshopModel->findArticleEshopByIb($id);
                $kosik[$id]['pocet'] = 0;
                $kosik[$id]->pocet++;
                // akce sleva tady dát hodnoty z db + třeba zboži jako dárek
                $data = $this->eshopModel->akce();
                $kosik[$id]['SlevaAkceCena'] = $data->cena;
                $kosik[$id]['SlevaAkce'] = $data->akce;
            }

            $ar = [];
            foreach ($kosik as $index => $content) {
                $ar[] = $kosik["$index"];
            }

        }
    }
    public function handleKosikSmaz()
    {
            $kosik = $this->getSession('kosik');
            foreach ($kosik as $index => $value) {
                unset($kosik["$index"]);
            }

            $this->template->kosik = $kosik;
            $this->redrawControl('kosik');
    }
    public function handleKosikSmazPolozku($id)
    {
        $kosik = $this->getSession('kosik');
        $id = $this->getParameter('idcko');
        foreach ($kosik as $index => $value) {
            if ($value->id == $id) {
                unset($kosik["$index"]);
            }
        }
        $this->template->kosik = $kosik;
    }
    public function handlePocetKosik()
    {
        $id = $this->getParameter('idcko');
        $minus = $this->getParameter('minus');
        $plus = $this->getParameter('plus');
        $sklad = $this->eshopModel->skladId($id);
        if(!$sklad){
            $this->redirect('this');
            exit;
        }
        $pocetSklad = $sklad['sklad'];
        if ($this->isAjax()) {

            $mySection = $this->getSession('kosik');
            if($minus){
                foreach($mySection as $index => $value){
                    if($value->id == $id){
                        $value->pocet-- ;
                    }
                    if($value->pocet == 0){
                        unset($mySection["$index"]);
                    }
                }
            }
            if($plus){
                foreach($mySection as $index => $value){
                    if($value->id == $id){
                        if($pocetSklad <= $value->pocet++){
                            $value->pocet = $pocetSklad;
                        }else{
                            $value->pocet;
                        }
                    }
                }
            }

            $this->template->kosik = $mySection;
        }

    }




}