<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use App\Model\ArticleManager;
use App\Services\TransportForm;
use App\Services\NameForm;
use App\Services\FormularForm;
use App\Services\EshopForm;
use App\Services\NewFormularForm;
use App\Services\UsersForm;
use App\Services\EmailUserForm;
use App\Services\ArticleForm;
use App\Services\CommentForm;
use App\Model\ShortCodeManager;
use Nette\Database\Table\Selection;
use Nette\Application\UI\Multiplier;
use Nette\Utils\Image;
use Nette\Utils\FileSystem;
use App\Model\PostManager;
use App\Model\UsersManager;
use App\Model\EshopModel;
use Nette\Utils\Strings;


final class AdminPresenter extends Nette\Application\UI\Presenter
{

    private $articleManager;
    /** @var ShortCodeManager @inject */
    private $shortCodeManager;
    /** @var NameForm @inject */
    public $nameFormFactory;
    /** @var UsersForm @inject */
    public $usersFormFactory;
    /** @var EmailUserForm @inject */
    public $emailUserFormFactory;
    /** @var ArticleForm @inject */
    public $articleFormFactory;
    /** @var CommentForm @inject */
    public $commentFormFactory;
    /** @var FormularForm @inject */
    public $formularFormFactory;
    /** @var NewFormularForm @inject */
    public $newFormularFormFactory;
    /** @var EshopForm @inject */
    public $eshopForm;
    /** @var TransportForm @inject */
    public $transportForm;

    private $postManager;
    private $usersManager;
    private $eshopModel;

    public function __construct(EshopModel $eshopModel,ArticleManager $articleManager, ShortCodeManager $shortCodeManager,PostManager $postManager,UsersManager $usersManager )
    {
        $this->articleManager = $articleManager;
        $this->shortCodeManager = $shortCodeManager;
        $this->postManager = $postManager;
        $this->usersManager = $usersManager;
        $this->eshopModel = $eshopModel;
    }
    protected function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isAllowed('backend')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }
    protected function createComponentTransportForm()
    {
        $form = $this->transportForm->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $id = $this->getParameter('id');

            if($form->isSuccess()){
                if($form['new']->isSubmittedBy()){
                    $this->eshopModel->insertTransport($values);
                }
                if($form['send']->isSubmittedBy()){
                    if($id){
                        $this->eshopModel->updateTransport($values,$id);
                    }elseif($id == 0){
                        $this->eshopModel->updateTransportZasilkovna($values);
                        $this->flashMessage("Zásilkovna byla upravena", 'alert-success');
                        $this->redirect('this');
                        exit;
                    }
                }
            }

            $this->flashMessage("Doprava byla upravena", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentCommentForm()
    {
        $form = $this->commentFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->flashMessage("Komentář byl upraven", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentNameForm()
    {
        $form = $this->nameFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->flashMessage("Jméno galerie bylo změněno na ".$values->name, 'alert-success');
            $id = intval($values->id);
            $this->shortCodeManager->renameGalerie($values->name,$id);
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentUsersForm()
    {
        $form = $this->usersFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $id = $this->getParameter('id');
            if($id){ $this->flashMessage("Uživatel byl úspěšně upraven", 'alert-success');
            }else{$this->flashMessage("Uživatel byl úspěšně přidán", 'alert-success');}
            $this->redirect('Admin:users');
            exit;
        };
        return $form;
    }
    protected function createComponentEmailUserForm()
    {
        $form = $this->emailUserFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {

            $this->flashMessage("Uživateli byl úspěšně zaslán email", 'alert-success');
            $this->redirect('Admin:users');
            exit;
        };
        return $form;
    }
    protected function createComponentNameSliderForm()
    {
        $form = $this->nameFormFactory->create();
        $form->addText('time', 'Čas:')
            ->setRequired('Prosím vyplňte čas.');
        $form->addSubmit('timeSub', 'Uložit');
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            if($form->isSuccess()){
                $id = intval($values->id);
                if($form['timeSub']->isSubmittedBy()){
                    $this->shortCodeManager->timeSlider($values->time,$id);
                    $this->flashMessage("Čas slideru byl změněn na ".$values->time, 'alert-success');
                }
                if($form['send']->isSubmittedBy()){
                    $this->shortCodeManager->renameSlider($values->name,$id);
                    $this->flashMessage("Jméno slideru bylo změněno na ".$values->name, 'alert-success');
                }
            }
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentAddTextSliderForm(): Multiplier
    {
        return new Multiplier(function ($itemId) {
            $form = new Nette\Application\UI\Form;
            $id = intval($itemId);
            $row = $this->shortCodeManager->getSliderImgText($id);
            $form->addTextArea('text', 'Obsah:')->setDefaultValue($row->text);
            $form->getElementPrototype()->onsubmit('tinyMCE.triggerSave()');
            $form->addHidden('itemId', $itemId);
            $form->addSubmit('send', 'Uložit text');
            $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
                $id = intval($values->itemId);
                $this->shortCodeManager->addTextSlider($id, $values->text);
                $this->flashMessage("Text byl přidán", 'alert-success');
                $this->redirect('this');
                exit;
            };
            return $form;
        });
    }
    protected function createComponentArticleForm()
    {
        $form = $this->articleFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $id = intval($values->article_id);
            if($id){
                $this->articleManager->editArticle($id,$values);
                $this->flashMessage("<a href='../$values->url' class='link-success'>Uloženo, ukázat!</a>", 'alert-success');
            }else{
                $this->articleManager->newArticle($values);
                $this->flashMessage("nova", 'alert-success');
            }
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentLogoForm(): Form
    {
        $form = new Form;
        $form->addText('name', 'Název:');
        $form->addText('img', 'Obrázek:');
        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            if($values->name == '') { $values->name = null; } if($values->img == '') { $values->img = null; }
            $this->articleManager->editLogo($values);
            $this->flashMessage("Logo bylo úspěšně přidáno.", 'alert-success');
            $this->redirect('this');exit;
            };
        return $form;
    }
    protected function createComponentContactAdminForm(): Form
    {
        $form = new Form;
        $form->addText('title', 'Popisek:');
        $form->addText('titleemail', 'Nazev emailu:');
        $form->addText('content', 'Zprava:');
        $form->addText('sendbtn', 'Tlačitko odeslat:');
        $form->addEmail('email', 'Email:');

        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->articleManager->editShortKontakt($values);
            $this->flashMessage("Kontaktní formulář byl upraven.", 'alert-success');
            $this->redirect('this');exit;
        };
        return $form;
    }
    protected function createComponentNewFormularForm(): Form
    {
        $form = $this->newFormularFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->flashMessage("Formulář byl upraven", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentFormularForm(): Form
    {
        $form = $this->formularFormFactory->create();
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->flashMessage("Komentář byl upraven", 'alert-success');
            $id = intval($values->id);
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentBlogNewForm(): Form
    {
        $form = new Form;
        $form->addText('url', 'Url:')
            ->addRule(Form::PATTERN, 'Není validni url', '.*[a-z]')
            ->setRequired('Vyplňte URL');
        $form->addText('title', 'Titulek:')
            ->setRequired('Titulek prosím');
        $form->addText('img', 'Obrázek:');
        $form->addHidden('author', 'Author:')->setRequired('Chyba author');
        $form->addTextArea('content', 'Obsah:')
            ->setHtmlAttribute('class', 'tinyMCE');
        $form->addSubmit('send', 'Uložit');
        $form->onValidate[] = function (UI\Form $form) {
            $id = $this->getParameter('id');
            $values = $form->getValues();
            $url = $values->url;
            if ($id) {
                $checkURL = $this->postManager->check($url,$id);
                if ($checkURL == true) {
                    $form->addError('URL je již použito! Změnte URL.');
                }
            } else {
                $checkURL = $this->postManager->checkUrl($url);
                if ($checkURL == true) {
                    $form->addError('URL je již použito! Změnte URL.');
                }
            }
        };

        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $postId = $this->getParameter('id');
            if ($postId) {
                $this->postManager->editBlog($values,$postId);
                $this->flashMessage("Blog byl upraven. <a href='../blog/clanek/$values->url'>Ukázat</a>", 'alert-success');
                $this->redirect('this');exit;
            }else{
            $this->postManager->newBlog($values);
            $this->flashMessage("Blog byl vytvořen. <a href='../blog/clanek/$values->url'>Ukázat</a>", 'alert-success');
            $this->redirect('this');exit;
            }
        };
        return $form;
    }
    protected function createComponentCategoryForm(): Form
        {
            $form = new Form;
            $id = $this->getParameter('id');
            if ($id) {
                $category = [];

                $dis = [];
                $data = $this::CategoryTreeSelect();
                $category += [0 => 'Nová hlavní kategorie'];
                foreach ($data as $cat) {
                    $category += [$cat['id'] => Nette\Utils\Html::el()->setHtml($cat['name'])];
                    if ($cat['id'] == $id) {
                        $dis += [$cat['id']];
                    }
                }
                $form->addSelect('parent_id', 'Kategorie:', $category)
                    ->setDisabled($dis)->setDefaultValue($id);
            } else {
                $category = [];
                $category += [0 => 'Nová hlavní kategorie'];
                $data = $this::CategoryTreeSelect();
                foreach ($data as $cat) {
                    $category += [$cat['id'] => Nette\Utils\Html::el()->setHtml($cat['name'])];
                }
                $form->addSelect('parent_id', 'Kategorie:', $category);//->setDefaultValue(0);
            }

            $form->addText('name', 'Jméno:')
                ->setRequired('Vyplňte jméno');
            $form->addSubmit('send', 'Uložit a publikovat');
            $form->onSuccess[] = [$this, 'categoryFormSucceeded'];

            return $form;
        }
    public function categoryFormSucceeded(Form $form, array $values): void
        {
            $postId = $this->getParameter('id');
            if ($postId) {
                $values['slug'] = Strings::webalize($values['name']);
                $this->eshopModel->insertCategoryEdit($postId,$values);
            } else {
                $values['slug'] = Strings::webalize($values['name']);
                $this->eshopModel->insertCategory($values);
            }

            $this->flashMessage("Kategorie byl úspěšně přidána.", 'alert-success');
            $this->redirect('Admin:category');
            exit;
        }
    private function CategoryTreeSelect($parent = 0, $spacing = '', $user_tree_array = '')
    {
        if (!is_array($user_tree_array))
            $user_tree_array = array();
        $query = $this->eshopModel->categoryParrent($parent);
        if (count($query) > 0) {
            while ($row = $query->fetch()) {
                $user_tree_array[] = array("id" => $row->id, "name" => $spacing . $row->name );
                $user_tree_array = $this::CategoryTreeSelect($row->id, $spacing . '&nbsp;', $user_tree_array);
            }
        }
        return $user_tree_array;
    }
    protected function createComponentEshopForm(): Form
    {
        $form = new Form;
        $form->addText('name', 'Jméno:')
            ->setRequired('Vyplňte jméno');
        $form->addInteger('price', 'Cena:')
            ->addRule(Form::PATTERN, 'Cena musí mít číslo', '([0-9]+)')
            ->setRequired('Vyplňte cenu');
        $form->addInteger('dph', 'DPH:')
            ->addRule(Form::PATTERN, 'DPH musí mít 2 čísla', '([0-9]+)')
            ->setRequired(' Vyplňte DPH');
        $form->addInteger('sale', 'AKCE:')
            ->addRule(Form::PATTERN, 'Akce musí mít max 2 čísla vyjadřující hodnotu %', '([0-9]+)');
        $form->addText('img', 'Obrázek:')
            ->setRequired('Vyplňte obrázek');

        $id = $this->getParameter('id');

        $category = [];
        $categoryDis = [];
        $data = $this::CategoryTreeSelect();
        foreach ($data as $cat) {
            $category += [$cat['id'] => Nette\Utils\Html::el()->setHtml($cat['name'])];
        }

        if ($id) {
            $lastCategory = $this->eshopModel->eshopProductCategory($id);
            $sklad = $this->eshopModel->skladId($id);
            $form->addSelect('category', 'Kategorie:', $category)
                ->setDefaultValue($lastCategory->category_id)
                ->setRequired('Vyplňte kategorii');
            $form->addInteger('sklad', 'Sklad:')
                ->setDefaultValue($sklad->sklad)
                ->setRequired(' Vyplňte sklad');
        } else {
            $form->addSelect('category', 'Kategorie:', $category)
                ->setDisabled($categoryDis)
                ->setPrompt('Zvolte kategorii')
                ->setRequired('Vyplňte kategorii');
            $form->addInteger('sklad', 'Sklad:')
                ->setRequired(' Vyplňte sklad');
        }

        $form->addText('url', 'Url:')
            ->addRule(Form::PATTERN, 'Není validni url', '.*[a-z]')
            ->setRequired('Vyplňte url');
        $form->addText('title', 'Titulek:')
            ->setRequired('Titulek prosím');
        $form->addTextArea('content', 'Obsah:')
            ->setHtmlAttribute('class', 'tinyMCE');

        //$form->getElementPrototype()->onsubmit('tinyMCE.triggerSave()');
        //$form->onValidate[] = [$this, 'productValiding'];
        $form->addSubmit('send', 'Uložit a publikovat');
        $form->onSuccess[] = [$this, 'productFormSucceeded'];

        return $form;
    }
    public function productFormSucceeded(Form $form, array $values): void
        {
            $postId = $this->getParameter('id');

            if ($postId) {
                $post = $this->eshopModel->getProduct($postId);
                $sklad = $this->eshopModel->skladId($postId);
                $post->update([
                    "name" => $values['name'],
                    "price" => $values['price'],
                    "dph" => $values['dph'],
                    "title" => $values['title'],
                    "url" => $values['url'],
                    "content" => $values['content'],
                    "img" => $values['img'],
                    "sale" => $values['sale'],
                ]);
                $sklad->update([
                    "sklad" => $values['sklad'],
                ]);
                $productCategory = $this->eshopModel->eshopProductCategory($postId);
                $productCategory->update([
                    "category_id" => $values['category'],
                ]);
            } else {
                $this->eshopModel->insertProduct($values);
            }

            $this->flashMessage("Produkt byl úspěšně přidán.", 'alert-success');
            $this->redirect('this');
            exit;
        }
    protected function createComponentFooterForm()
    {
        $form = new Form;
        $form->addTextArea('content', 'Obsah:')
            ->setHtmlAttribute('class', 'tinyMCE');
        $form->addSubmit('send', 'Uložit a publikovat');
        $form->onSuccess[] = function (UI\Form $form, \stdClass $values) {
            $this->articleManager->updateFooter($values->content);
            $this->flashMessage("Footer byl upraven", 'alert-success');
            $this->redirect('this');
            exit;
        };
        return $form;
    }
    protected function createComponentObjednavkaForm(): Form
    {
            $form = new Form;
            $form->addText('jmeno', 'Jméno:')
                ->setRequired('Vyplňte jméno');
            $form->addText('prijmeni', 'Příjmení:')
                ->setRequired('Vyplňte příjmení');
            $form->addText('ulice', 'Ulice:')
                ->setRequired('Vyplňte ulici');
            $form->addText('mesto', 'Město:')
                ->setRequired('Vyplňte město');
            $form->addInteger('psc', 'PSČ:')
                ->setRequired('Vyplňte PSČ');
            $form->addText('telefon', 'Telefon:')
                ->setRequired('Vyplňte telefon');
            $form->addText('email', 'Email:')
                ->setRequired('Vyplňte email');
            $form->addText('doprava', 'Doprava:')
                ->setRequired('Vyplňte dopravu');
            $form->addInteger('dopravaCena', 'Cena dopravy:')
                ->setRequired('Vyplňte cenu');
            $form->addText('platba', 'Platba:')
                ->setRequired('Vyplňte platbu');
            $form->addInteger('platbaCena', 'Cena platby:')
                ->setRequired('Vyplňte cenu');
            $form->addText('cena_celkem', 'Cena celkem:')
                ->setRequired('Vyplňte celkovou cenu');
            $form->addText('akce', 'Akce:');
            $form->addText('poznamka', 'Poznámka:');
            $form->addText('datum', 'Datum:')
                //->setType('date')
                ->setHtmlAttribute('class', 'date-input')
                ->setRequired('Vyplňte datum');

            $objednavkyStav = [
                'nová' => 'Nová objednávka',
                'zpracovává se' => 'Zpracovává se',
                'prodáno' => 'Vyřízená objednávka',
                'reklamace' => 'Reklamace',
            ];
            $form->addSelect('stav', 'Stav:', $objednavkyStav);

            //$form->getElementPrototype()->onsubmit('tinyMCE.triggerSave()');
            $form->addSubmit('send', 'Uložit a publikovat');
            $form->onSuccess[] = [$this, 'objednavkaFormSucceeded'];

            return $form;
    }
    public function objednavkaFormSucceeded(Form $form, array $values): void
    {
            $postId = $this->getParameter('id');

            if ($postId) {
                $post = $this->eshopModel->objGet($postId);
                $post->update($values);
                $this->flashMessage("Objednávka byla úspěšně uložena.", 'alert-success');
                $this->redirect('this');
                exit;
            } else {
                $post = $this->database->table('objednavka')->insert($values);

                $valuesId = $form->getHttpData($form::DATA_TEXT, 'zboziId[]');
                $valuesPocet = $form->getHttpData($form::DATA_TEXT, 'pocet[]');
                $valuesCena = $form->getHttpData($form::DATA_TEXT, 'cena[]');
                $valuesJmeno = $form->getHttpData($form::DATA_TEXT, 'jmenoZbozi[]');

                $totalPrice = 0;
                $cenaDoprava = $form->getHttpData($form::DATA_TEXT, 'dopravaCena');
                $cenaPlatba = $form->getHttpData($form::DATA_TEXT, 'platbaCena');

                $totalPrice = $cenaDoprava + $cenaPlatba;

                foreach ($valuesId as $index => $value) {

                    $produkt = $this->database->table('product')->get($value);

                    $totalPrice += round($valuesCena[$index] * $valuesPocet[$index] * (1 + $produkt['dph'] / 100), 0);
                    $this->database->table('objednavka_produkt')->insert([
                        'name' => $valuesJmeno[$index],
                        'pocet' => $valuesPocet[$index],
                        'price' => $valuesCena[$index],
                        'dph' => $produkt['dph'],
                        'objednavka_id' => $post->id
                    ]);

                    $sklad = $this->database->table('sklad')->where('product_id', $value)->fetch();
                    $mnozstviSklad = $sklad['sklad'];
                    $noveSklad = $mnozstviSklad - $valuesPocet[$index];
                    if ($noveSklad < 0) {
                        $this->flashMessage("Zboží není skladem.", 'danger');
                        $this->redirect('Admin:nova-objednavka');
                        exit;
                    } else {
                        $sklad->update([
                            'sklad' => $noveSklad
                        ]);
                    }
                }

           $celkemCena = $this->database->table('objednavka')->get($post->id);
           $celkemCena->update(['cena_celkem' => $totalPrice]);

           $this->flashMessage("Objednávka byla úspěšně vytvořena.", 'success');
           $this->redirect('Admin:objednavka');
           exit;
      }

    }
    protected function beforeRender()
    {
        /** Logo a footer */
        $this->template->logoFooter = $this->articleManager->getLogoFooter();
        /** menu */
        $this->template->url = 'admin';
        /** mce editor galerie slider */
        $this->template->galeries = $this->shortCodeManager->getGalerie();
        $this->template->sliders =  $this->shortCodeManager->getSlider();
        /** admin menu */
        $httpRequest = $this->getHttpRequest();$url = $httpRequest->getUrl();
        $this->template->menuUrl = $url->getPathInfo();
    }
    private function mceEditor()
    {

    }
    public function renderPdf($id)
    {
    $this->template->url = 'admin';

    $id = $this->getParameter('id');
    $pdfObj = $this->eshopModel->objEx($id);
    //$pdfDodavatel = $this->database->table('subjekty')->where('active', 'ano')->fetch();


    $mpdf = new \Mpdf\Mpdf();

    foreach ($pdfObj as $pdf) {
        $mpdf->SetHeader('Objednávka č.' . $pdf->id);
        $mpdf->WriteHTML('<table style="width: 100%;margin-top: 50px"><tr><td></td><td></td><td></td><td></td></tr>');
        $mpdf->WriteHTML(
            "<tr><td>
                 <b>Odběratel</b><br />
                 $pdf->jmeno $pdf->prijmeni <br />
                 $pdf->ulice <br />
                 $pdf->mesto  PSČ $pdf->psc </td>"
        );
        $mpdf->WriteHTML(
            "<td colspan='3' style='text-align: right'>
                 <b>Dodavatel</b><br />
                 Firma<br />
                 ulice 666<br />
                 Praha 5  PSČ 50000<br /></td>
                 </tr>"
        );
        $mpdf->WriteHTML('</table>');
        $mpdf->WriteHTML('<table style="width: 100%;margin-top: 50px">');
        $mpdf->WriteHTML('<tr><th style="text-align: left;width:60%">Zboží</th><th style="text-align: left;width:10%">Počet</th><th style="text-align: left;width:10%">Cena/ks</th><th style="text-align: left;width:5%">DPH</th><th style="text-align: right;width:15%">Cena s DPH</th></tr>');


        foreach ($pdf->related('objednavka_produkt') as $objPro) {
            $mpdf->WriteHTML('<tr>');
            $mpdf->WriteHTML('<td>' . $objPro->name . '</td>');
            $mpdf->WriteHTML('<td>' . $objPro->pocet . ' ks</td>');
            $mpdf->WriteHTML('<td>' . $objPro->price . ' Kč</td>');
            $mpdf->WriteHTML('<td>' . $objPro->dph . ' %</td>');
            $mpdf->WriteHTML('<td style="text-align: right">' . (round($objPro->price * $objPro->pocet * (1 + $objPro->dph / 100), 0)) . ' Kč</td>');
            $mpdf->WriteHTML('</tr><tr><td>'.$objPro->objednavka->akce.'</td></tr><tr><td>'.$objPro->objednavka->poznamka.'</td></tr>');     }

        $mpdf->WriteHTML('<tr><td colspan="5"><br></td></tr>');
        $mpdf->WriteHTML('<tr><td colspan="4">Doprava: ' . $pdf->doprava . ' </td><td style="text-align: right">' . $pdf->dopravaCena . ' Kč</td></tr>');
        $mpdf->WriteHTML('<tr><td colspan="4">Platba: ' . $pdf->platba . ' </td><td style="text-align: right">' . $pdf->platbaCena . ' Kč</td></tr>');
        $mpdf->WriteHTML('<tr><td colspan="5"><hr></td></tr>');
        $mpdf->WriteHTML('<tr><td colspan="5" style="text-align: right">Cena celkem: ' . $pdf->cena_celkem . ' Kč</td></tr>');
        $mpdf->SetFooter('Ze dne: ' . $pdf->datum->format('d.m.Y'));
        $mpdf->WriteHTML('</table>');


    }
    $mpdf->Output();
    }
    public function renderDefault()
    {

    }
    public function renderBlog()
    {

    }
    public function renderUsers()
    {
        /** @var  $httpRequest */
        $factory = new Nette\Http\RequestFactory;
        $httpRequest = $factory->fromGlobals();
        $this->template->testUrl = $httpRequest->getQuery();
        $page = intval($this->getParameter('stranka'));
        if(!$page){
            $page =1;
        }
        // Vytáhneme si publikované články
        //$posts = $this->usersManager->findPublishedUsers();

        $role = $this->getParameter('role');
        $date = $this->getParameter('date');
        $name = $this->getParameter('name');
        $posts = $this->usersManager->findPublishedUsers($role,$date,$name);


        // a do šablony pošleme pouze jejich část omezenou podle výpočtu metody page
        $lastPage = 0;
        $this->template->users = $posts->page($page, 10, $lastPage);
        // a také potřebná data pro zobrazení možností stránkování
        $this->template->pageFirst = $page;
        $this->template->lastPage = $lastPage;

    }
    public function renderNewUser()
    {
        $this->template->users = $this->usersManager->getUsers();
    }
    public function renderEditComments($id)
    {
        $id = intval($this->getParameter('id'));
        if(!$id){
            $this->error('Komentař nebyl nalezen');
            exit;
        }
        $comm = $this->postManager->comments($id);
        if (!$comm) {
            $this->error('Komentař nebyl nalezen');
            exit;
        }
        $this->template->clanek = $comm->ref('posts','post_id')->title;
        $this->template->clanekUrl = $comm->ref('posts','post_id')->url;
        $this['commentForm']->setDefaults($comm->toArray());
    }
    public function renderCommentsblock()
    {
        $this->template->block = $this->postManager->commentsblock();
    }
    public function renderComments()
    {
        $page = intval($this->getParameter('stranka'));
        if(!$page){
            $page =1;
        }
        // Vytáhneme si publikované články
        $posts = $this->postManager->findPublishedComment();
        // a do šablony pošleme pouze jejich část omezenou podle výpočtu metody page
        $lastPage = 0;
        $this->template->post = $posts->page($page, 15, $lastPage);
        // a také potřebná data pro zobrazení možností stránkování
        $this->template->pageFirst = $page;
        $this->template->lastPage = $lastPage;
    }
    public function renderFooter()
    {
        $data = $this->articleManager->footer();
        $this->template->data = $data;
        $this['footerForm']->setDefaults($data);
    }
    public function renderKontakt()
    {
        $data = $this->articleManager->getShortKontakt();
        $this->template->data = $data;
        $this['contactAdminForm']->setDefaults($data);
    }
    public function renderformular()
    {
        // tohle dat do formulare a vygenerovat inputy
        $data = $this->articleManager->getShortFormular();
        $this->template->formular = $data;
        $this->template->items = $this->articleManager->getShortFormularInput();;
    }
    public function renderMenu()
    {

        $this->template->article = $this->articleManager->getArticlesMenu();
        $this->template->thems = $this->articleManager->getLogo();
        $this->template->allArticle = $this->articleManager->menuArticle();
        $menu = $this->articleManager->getMenu();
        $this->template->menu = $menu;
        $parrent[] = '';
        foreach ($menu as $index => $polozka) {
            $parrent[$index] = $this->articleManager->getMenuParrent($polozka->article_id);
        }

        $this->template->parrent = $parrent;
    }
    public function renderPage()
    {

    }
    public function renderPageList()
    {
        $this->template->menu = $this->articleManager->getArticles();
        $this->template->model = $this->articleManager;
    }
    public function renderSlider()
    {
        $this->template->slider = $this->shortCodeManager->getSlider();
    }
    public function renderGalerie()
    {
        $this->template->galerie = $this->shortCodeManager->getGalerie();
    }
    public function renderCategory()
    {
        $this->template->category = $this->eshopModel->categoryStromEdit();
    }
    public function renderObjednavka()
    {
        $this->template->objednavky = $this->eshopModel->objQuery();
    }
    public function renderDoprava()
    {
        $this->template->transport = $this->eshopModel->transport();
    }
    public function handleNaseptavac($value)
    {
        $hledat = $this->getParameter('value');
        if ($this->isAjax()) {
            if(strlen($hledat) <= 2 && strlen($hledat) != 0){ exit; }
            if(strlen($hledat) == 0){
                $result = false;
            }else{
                $result = $this->articleManager->searchPageWhisperer($hledat);
                if(empty($result)){ $result = false; }
            }
            $this->template->naseptavac = $result;
            $this->redrawControl('naseptavac');
        }
    }
    public function handleUploadImg()
    {
        if (!empty($_FILES)) {
            $filePath = '/img/product/';
            $this->request->getFiles()['file']->toImage();
            $fileName = $this->request->getFiles()['file']->getName();
            $this->request->getFiles()['file']->move('.' . $filePath . $fileName);
            $image = Image::fromFile('.' . $filePath . $fileName);
            $image->resize(250, 170, Image::SHRINK_ONLY | Image::EXACT);
            $image->sharpen();
            $image->save('.' . $filePath . $fileName);
            $message = '<input name="img" value="' . $filePath . $fileName . '" type="hidden" />
                    <img src="' . $filePath . $fileName . '" class="img-fluid" />';

        } else {
            $message = '<div class="alert alert-danger">Vyberte soubor</div>';
        }

        $this->payload->message = $message;
        $this->redrawControl('UploadImg');
    }
    public function handleUploadImgPost()
    {
        if (!empty($_FILES)) {
            $filePath = '/img/blog/avatar/';
            $this->request->getFiles()['file']->toImage();
            $fileName = $this->request->getFiles()['file']->getName();
            $this->request->getFiles()['file']->move('.' . $filePath . $fileName);
            $image = Image::fromFile('.' . $filePath . $fileName);
            $image->resize(210, 140, Image::SHRINK_ONLY | Image::EXACT);
            $image->sharpen();
            $image->save('.' . $filePath . $fileName);
            $message = '<input name="img" value="' . $filePath . $fileName . '" type="hidden" />
                            <img src="' . $filePath . $fileName . '" class="img-fluid" />';

        } else {
            $message = '<div class="alert alert-danger">Vyberte soubor</div>';
        }

        $this->payload->message = $message;
        $this->redrawControl('UploadImg');
    }
    public function handleUploadImgLogo()
    {
        if (!empty($_FILES)) {
            $filePath = '/img/logo/';
            $this->request->getFiles()['file']->toImage();
            $fileName = $this->request->getFiles()['file']->getName();
            $this->request->getFiles()['file']->move('.' . $filePath . $fileName);
            $image = Image::fromFile('.' . $filePath . $fileName);
            $image->resize(null, 100, Image::SHRINK_ONLY);
            $image->sharpen();
            $image->save('.' . $filePath . $fileName);
            $message = '<input name="img" value="' . $filePath . $fileName . '" type="hidden" />
                        <img src="' . $filePath . $fileName . '" class="img-fluid" />';
        } else {
            $message = '<div class="alert alert-danger">Vyberte soubor</div>';
        }
        $this->payload->message = $message;
        $this->redrawControl('UploadImg');
    }
    public function handlePoradi(array $value)
    {
        $value = $this->getHttpRequest()->getPost('novePoradi');
        if ($this->isAjax()) {
            $this->articleManager->listMenu($value);
            $this->payload->message = $value;
        }
    }
    public function handleParrent($id, $parrentId)
    {
        $id = $this->getHttpRequest()->getPost('id');
        $parrentId = $this->getHttpRequest()->getPost('parrentId');
        if ($parrentId == 'null') {
            $parrentId = NULL;
        }
        if ($this->isAjax()) {
            $this->articleManager->pattern($parrentId,$id);
        }
    }
    /* edit objednavka */
    public function handlePridatZbozi($zbozi, $id)
    {
    $zbozi = $this->getParameter('zbozi');
    $id = $this->getParameter('id');

    $produkt = $this->eshopModel->getProduct($zbozi);
    $kontrola = $this->eshopModel->productPolozka($id);

    if (!$zbozi) {
        $this->redirect('this');
        exit;
    }

    $je = false;


    foreach ($kontrola as $index => $kon) {
        if ($produkt['name'] == $kon['name']) {
            $this->template->mySection = '<div class="btn btn-sm btn-danger">Zboží je již v objednávce</div>';
            $this->redrawControl('pridatZbozi');
            $je = true;
        }
    }


    if ($this->isAjax() && $je == false) {

        $row = $this->eshopModel->insertProductObj($produkt,$id);

        if ($row == true) {
            $this->template->mySection = '<div class="btn btn-sm btn-success">Přidávám zboží</div>';
            $this->redrawControl('pridatZbozi');
        }
    }

    }
    public function handleUlozZbozi($name, $id, $obj)
    {
    $name = $this->getParameter('name');
    $id = $this->getParameter('produktId');
    $pocet = $this->getParameter('pocet');
    $price = $this->getParameter('price');

        if ($this->isAjax()) {
                $this->eshopModel->ulozZbozi($name,$id,$pocet,$price);
                $this->template->zprava = '<script>location.reload();</script>';
                $this->redrawControl('ulozZbozi');
        }

    }
    public function handleUlozCena($doprava, $platba, int $id)
    {
    $doprava = $this->getParameter('doprava');
    $platba = $this->getParameter('platba');
    $id = $this->getParameter('id');

    if ($this->isAjax()) {

        $row = $this->eshopModel->ulozCena($id,$doprava,$platba);

        if ($row) {
            $this->template->zprava = '<script>location.reload();</script>';
            $this->redrawControl('ulozZbozi');
        }
    }

    }
     public function handleSkladUloz($id)
    {
    $sklad = $this->getParameter('sklad');
    $id = $this->getParameter('idSklad');

    if ($this->isAjax()) {
        $row = $this->eshopModel->skladInsert($id,$sklad);

        if ($row) {
            $this->template->zprava = '<script>location.reload();</script>';
            $this->redrawControl('ulozZbozi');
        }
    }

    }
    /* transport */
    public function actionZasilkovna($values)
    {
        $this->eshopModel->zasilkovna($values);
        $this->redirect("Admin:doprava");
        exit;
    }
    public function actionEditTransport(int $id): void
    {
        $transport = $this->eshopModel->transportGet($id);
        if (!$transport) {
            $this->error('Doprava nebyla nalezena');
        }
        $this->template->id = $id;
        $this['transportForm']->setDefaults($transport->toArray());
    }
    public function actionDeleteTransport(int $id): void
    {
        if($id == 0){
            $this->flashMessage("Zásilkovna je systémová doprava nelze smazat", 'alert-warning');
            $this->redirect("Admin:doprava");
            exit;
        }
        $transport = $this->eshopModel->transportGet($id);
        if (!$transport) {
            $this->error('Doprava nebyla nalezena');
        }
        $this->eshopModel->transportDelete($id);
        $this->flashMessage("Doprava byla smazána", 'alert-success');
        $this->redirect("Admin:doprava");
        exit;
    }
    /* objednavka */
    public function actionEditObj(int $id): void
    {

        $cenaObj = $this->eshopModel->cenaNoveObj($id);
        $cenaObjDopravaPlatba = $this->eshopModel->cenaObj($id);

        $cenaCelkem = 0;
        foreach ($cenaObj as $cena) {
            $cenaCelkem += $cena['total_price'];
        }

        $this->template->cena_celkem = $cenaCelkem + $cenaObjDopravaPlatba->cenaDohromady;

        $objednavka = $this->eshopModel->objGet($id);
        if (!$objednavka) {
            $this->error('Objednávka nebyla nalezena');
        }

        $produkty = [];

        foreach ($objednavka->related('objednavka_produkt')->order('id DESC') as $objPro) {
            $this['objednavkaForm']->setDefaults($objPro->toArray());
            $produkty[] = $objPro->toArray();
        }

        $this->template->objProdukt = $produkty;
        $this->template->id = $id;
        $this->template->noveZbozi = $this->eshopModel->productName();

        $this['objednavkaForm']->setDefaults($objednavka->toArray());

    }
    public function actionDeleteZbozi(int $id, int $objId): void
    {
        $id = $this->getParameter('id');
        $objId = $this->getParameter('objId');
        $this->eshopModel->deleteZbozi($id,$objId);
        $this->redirect("edit-obj?id=".$objId);
        exit;
    }
    /* categorie */
    public function actionEditCategory(int $id): void
    {
      $category = $this->eshopModel->categorRoutery($id);
      if (!$category) {
       $this->error('Kategorie nebyla nalezena');
      }
      $this->template->name = $category->name;
      $this['categoryForm']->setDefaults($category->toArray());
    }
    public function actionDeleteCategory($id)
    {
        $id = $this->getParameter('id');
        $check = $this->eshopModel->checkCategory($id);
        if ($check) {
            $this->flashMessage("Kategorie nelze smazat! Je použita u produktů. Odstraňte produkty z kategorie. Nestačí pouze přejmenovat název kategorie?", 'alert-warning');
            $this->redirect("Admin:category");
            exit;
        } else {
            $checkParent = $this->eshopModel->checkParent($id);
            if ($checkParent) {
                $this->flashMessage("Kategorie nelze smazat! Je použita, jako hlavní kategorie. Odstraňte podkategorie. Nestačí pouze přejmenovat název kategorie?", 'alert-warning');
                $this->redirect("Admin:category");
                exit;
            } else {
                $this->eshopModel->deleteCategory($id);
                $this->flashMessage("Kategorie byla smazána", 'alert-success');
                $this->redirect("Admin:category");
                exit;
            }
        }

    }
    /* POST*/
    public function actionEditPost(int $id): void
    {
       $post = $this->postManager->getIdPost($id);
        if (!$post) {
           $this->error('Příspěvek nebyl nalezen');
        }
       $this->template->obr = $post->img;
       $this['blogNewForm']->setDefaults($post->toArray());
    }
    public function actionRecommended($id)
    {
        $id = $this->getParameter('id');
        $rec = $this->getParameter('rec');

        $po = $this->postManager->recommend($id);
        if ($po->recommended == 'ne') {
            $rec = null;
            $this->flashMessage("Příspěvek byl odstraněn z doporučených", 'alert-success');
            $this->redirect("Homepage:?url=blog");
            exit;
        }
        $this->postManager->recommendUp($rec,$id);
        $this->flashMessage("Příspěvek byl doporučen", 'alert-success');
        $this->redirect("Homepage:?url=blog");
        exit;
    }
    public function actionDeletePost($id)
    {
        $id = $this->getParameter('id');
        $this->postManager->deleteRecommennd($id);
        $this->flashMessage("Příspěvek byl smazán", 'alert-success');
        $this->redirect("Homepage:?url=blog");
        exit;
    }
    /** komentare */
    public function actionDeleteComment($id)
    {
        $id = $this->getParameter('id');
        if ($id) {
            $this->postManager->deleteComment($id);
            $this->flashMessage("Komentář byl smazán", 'alert-success');
            $this->redirect("Admin:comments");
            exit;
        }
    }
    public function actionDeleteBlock($id)
    {
        $id = $this->getParameter('id');
        if ($id) {
            $this->postManager->deleteBlock($id);
            $this->flashMessage("IP adresa je odblokována", 'alert-success');
            $this->redirect("Admin:commentsblock");
            exit;
        }

    }
    public function actionBlockIp($id)
    {
        $id = $this->getParameter('id');
        if ($id) {
            $r = $this->postManager->blockId($id);
            if($r === false) {
                $this->flashMessage("IP adresa je již blokována", 'alert-warning');
                $this->redirect("Admin:comments");
                exit;
            }
            if($r === true) {
                $this->flashMessage("IP adresa je blokována", 'alert-success');
                $this->redirect("Admin:comments");
                exit;
            }
        }
    }

    public function actionAddMenu($article_id)
    {
    $id = intval($this->getParameter('article_id'));
    $url = $this->getParameter('url');
    $this->articleManager->menuAdd($id);
    $this->flashMessage("Stránka byla přidána do menu", 'alert-success');
    if (!$url) {
        $this->redirect("Admin:menu");
    } else {
        $this->redirect('Admin:page-list');
    }
    exit;
    }
    public function actionRemoveMenu($article_id)
    {
        $id = intval($this->getParameter('article_id'));
        $url = $this->getParameter('url');
        $this->articleManager->menuRemove($id);
        $this->flashMessage("Stránka byla odstraněna z menu", 'alert-success');
        if (!$url) {
            $this->redirect("Admin:menu");
        } else {
            $this->redirect('Admin:page-list');
        }
        exit;
    }
    public function actionEditProduct(int $id):void
    {
        $product = $this->eshopModel->getProduct($id);
        if (!$product) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->obr = $product->img;
        $this['eshopForm']->setDefaults($product);
    }
    public function actionPageEdit(int $id):void
    {
        $article = $this->articleManager->getArticleId($id);
        if (!$article) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->article = $article;
        $this['articleForm']->setDefaults($article);
    }
    public function actionSlider($content, $name, $time)
    {
        $name = $this->getParameter('name');
        $content = $this->getParameter('content');
        $time = $this->getParameter('time');

        if ($content) {
            $this->shortCodeManager->insertSlider($name,$content,$time);
        }
    }
    public function actionGalerie($content, $name)
    {
    $name = $this->getParameter('name');$content = $this->getParameter('content');
     if ($content) {
        $this->shortCodeManager->insertGalerie($name,$content);
     }
    }
    public function actionGaleriePlusImg()
    {
        $idGal = $this->getParameter('idGal');
        $content = $this->getParameter('content');
        $this->shortCodeManager->galeriePlusImg($content,intval($idGal));
    }
    public function actionSliderPlusImg()
    {
        $idGal = $this->getParameter('idGal');
        $content = $this->getParameter('content');
        $this->shortCodeManager->sliderPlusImg($content,intval($idGal));
    }
    public function actionSmazatGalerie(int $id) :void
    {
        $id = $this->getParameter('id');
        if($id) {
        $this->shortCodeManager->deleteGalerie($id);
          if($this->shortCodeManager->deleteGalerie($id) === true)
          {
            $this->flashMessage("Galerie byla odstraněna", 'alert-success');
            $this->redirect("Admin:galerie");
            exit;
          }
        }
    }
    public function actionSmazatSlider(int $id) :void
    {
        $id = $this->getParameter('id');
        if($id) {
            $this->shortCodeManager->deleteSlider($id);
            if($this->shortCodeManager->deleteSlider($id) === true)
            {
                $this->flashMessage("Slider byl smazán", 'alert-success');
                $this->redirect("Admin:slider");
                exit;
            }
        }
    }
    public function actionEditSlider(int $id) :void
    {
        $slider = $this->shortCodeManager->getSliderId($id);
        $img = $this->shortCodeManager->getSliderImg($id);

        $this->template->slider = $slider;
        $this->template->img = $img;

        if (!$slider) {
            $this->error('Slider nebyla nalezena');
        }

        $row = $this->shortCodeManager->getSliderArray($id);
        $this['nameSliderForm']->setDefaults($row);
    }
    public function actionEditGalerie(int $id) :void
    {
        $galerie = $this->shortCodeManager->getGalerieId($id);
        $img = $this->shortCodeManager->getGalerieImg($id);
        $this->template->galerie = $galerie;
        $this->template->img = $img;

        if (!$galerie) {
            $this->error('Galerie nebyla nalezena');
        }

        $row = $this->shortCodeManager->getGalerieArray($id);
        $this['nameForm']->setDefaults($row);

    }
    public function actionSmazatGalerieImg(int $id, int $idGal) :void
    {
        if($id) {
            $this->shortCodeManager->deleteGalerieImg($id);
            if($this->shortCodeManager->deleteGalerieImg($id) === true)
            {
                $this->flashMessage("Obrázek byl odstraněn z galerie", 'alert-success');
                $this->redirect("edit-galerie?id=".$idGal);
                exit;
            }
        }
    }
    public function actionSmazatSliderImg(int $id, int $idGal) :void
    {
        if($id) {
            $this->shortCodeManager->deleteSliderImg($id);
            if($this->shortCodeManager->deleteSliderImg($id) === true)
            {
                $this->flashMessage("Obrázek byl odstraněn ze slideru", 'alert-success');
                $this->redirect("edit-slider?id=".$idGal);
                exit;
            }
        }
    }
    public function actionDeleteArticle(int $article_id) :void
    {
        if($article_id) {
            $this->articleManager->deleteArticle($article_id);
            if($this->articleManager->deleteArticle($article_id) === true)
            {
                $this->flashMessage("Stránka byla odstraněna", 'alert-success');
                $this->redirect("page-list");
                exit;
            }
        }
    }
    /** formular */
    public function actionDeleteInputFormular($id): void
    {
        $this->shortCodeManager->deleteInputFormular($id);
        $this->flashMessage("Input byl smazán", 'alert-success');
        $this->redirect("formular");
        exit;
    }
    /** users */
    public function actionEditUsers($id): void
    {
        $u = $this->usersManager->getUsersId($id);
        if (!$u) {
            $this->error('Uživatel nebyl nalezen');
            exit;
        }
        $this['usersForm']->setDefaults($u->toArray());
    }
    public function actionEmailUsers($id): void
    {
        $u = $this->usersManager->getUsersId($id);
        if (!$u) {
            $this->error('Uživatel nebyl nalezen');
            exit;
        }
        $this->template->users = $u;
        $this['emailUserForm']->setDefaults($u->toArray());
    }
    public function actionDeleteUsers($id): void
    {
        $this->usersManager->deleteUsersId($id);
        $this->flashMessage("Uživatel byl smazán", 'alert-success');
        $this->redirect("Admin:users");
        exit;
    }

    /** Menu admin */
    public function handleThems($id)
    {
    $id = $this->getParameter('id');
    $thems = $this->getParameter('thems');
      if ($this->isAjax()) {
        if ($thems) {
            $this->articleManager->logo($id,$thems);
        }
      }
    }

}