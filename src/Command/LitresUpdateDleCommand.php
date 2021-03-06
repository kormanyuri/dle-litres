<?php

namespace App\Command;

use App\Entity\DleImages;
use App\Entity\DlePost;
use App\Entity\LitresJsonData;
use App\Utils\Log;
use App\Utils\Picture;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LitresUpdateDleCommand extends Command
{
    /**
     * @var EntityManager
     */
    private $em;

    protected static $defaultName = 'litres:update-dle';

    /**
     * LitresImportCommand constructor.
     * @param null $name
     * @param EntityManagerInterface $em
     */
    public function __construct($name = null, EntityManagerInterface $em)
    {
        parent::__construct($name);
        $this->em = $em;

    }

    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var LitresJsonData $book
         */
        $io = new SymfonyStyle($input, $output);
//        $arg1 = $input->getArgument('arg1');
//
//        if ($arg1) {
//            $io->note(sprintf('You passed an argument: %s', $arg1));
//        }
//
//        if ($input->getOption('option1')) {
//            // ...
//        }
        $criteria = new Criteria();
        $criteria->where($criteria->expr()->andX(
                     $criteria->expr()->neq('local_id', -1),
                     $criteria->expr()->orX(
                         $criteria->expr()->eq('local_id', 0),
                         $criteria->expr()->eq('need_local_update', true)
                     )
                 ))
                 ->setMaxResults(100);

        $books = $this->em->getRepository(LitresJsonData::class)
                            ->matching($criteria);
        $log = new Log();

        foreach ($books as $book) {
            $data = $book->getData();

            $mainAuthor = $this->getMainAuthor($data);

            $litres_url = $data['@attributes']['url'];
            $hub_id = $book->getHubId();
            $partner_id = getenv('LITRES_PARTNER_ID');
            $litres_link = 'https://www.litres.ru/' . ($litres_url != '' ? $litres_url . '?lfrom=' : 'pages/biblio_book/?art=' . $hub_id . '&lfrom=' ) . $partner_id;
            //echo $litres_link . "\n";
            $local_categories = 99;

            $book_title = $data['book-title']['@attributes']['title'];
            $firstName = is_array($mainAuthor['first-name']) ? '' : $mainAuthor['first-name'];
            $lastName = is_array($mainAuthor['last-name']) ? '' : $mainAuthor['last-name'];

            $title = stripslashes(trim($firstName . ' ' . $lastName) . ' - ' . $book_title);

            $alt_name = $this->totranslit(mb_convert_encoding(stripslashes($book_title),'windows-1251','UTF-8'), true, false );

            $dir_name = date("Y-m");
            $pic_name = time() . "_" . $data['@attributes']['id'] . '.jpg';
            $annotationObj = $this->getAnnotation($data);

            if (is_array($annotationObj)) {
                if (isset($annotationObj['strong'])) {

                    if (is_array($annotationObj['strong'])) {
                        if (isset($annotationObj['strong']['emphasis'])) {
                            $annotation = $annotationObj['strong']['emphasis'];
                        } else {
                            echo "==annotation==\n";
                            print_r($annotationObj);
                            echo "==annotation==\n";
                            $annotation = '';
                            //throw new  \Exception('Annotation have array');
                        }
                    } else {
                        $annotation = $annotationObj['strong'];
                    }
                } elseif (isset($annotationObj['style'])) {

                    if (is_array($annotationObj['style'])) {
                        if (isset($annotationObj['style']['emphasis'])) {
                            $annotation = $annotationObj['style']['emphasis'];
                        } else {
                            echo "==annotation==\n";
                            print_r($annotationObj);
                            echo "==annotation==\n";
                            $annotation = '';
                            //throw new  \Exception('Annotation have array');
                        }
                    } else {
                        $annotation = $annotationObj['style'];
                    }

                } elseif (isset($annotationObj['emphasis'])) {
                    $annotation = $annotationObj['emphasis'];
                } else {
                    echo "==annotation==\n";
                    print_r($annotationObj);
                    echo "==annotation==\n";
                    $annotation = '';
                    //throw new  \Exception('Annotation have array but not have "strong" and "style" items');
                }
            } else {
                $annotation = $annotationObj;
            }

            if (is_array($annotation)) {
                echo "==annotation==\n";
                print_r($annotationObj);
                echo "==annotation==\n";
                throw new  \Exception('Annotation have array');
            }


            $full_story = '<div style="text-align:center;"><!--dle_image_begin:http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '|--><img src="http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '" alt="Джейн Фэллон - Дважды два - четыре" title="' . $title . '" /><!--dle_image_end--></div><br />
			<div style="text-align:center;">' . nl2br(mb_substr($annotation, 0, 400)) . '<br /><br />
			<b>Название:</b> ' . trim($book_title) . '<br />
			<b>Автор:</b> ' . trim($firstName . ' ' . $lastName) . '<br />
			' . ($data['@attributes']['publisher'] != '' ? '<b>Издательство:</b> ' . $data['@attributes']['publisher'] . '<br />' : '') . '
			' . (isset($data['publish-info']) && isset($data['publish-info']['year']) && $data['publish-info']['year'] > 0 ? '<b>Год:</b> ' . $data['publish-info']['year'] . '<br />' : '') . '
			<b>Формат:</b> RTF/FB2<br />
			<b>Язык:</b> Русский
			</div><br /><br />';


            $short_story = '<div style="text-align:center;"><!--dle_image_begin:http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '|--><img src="http://www.vipbook.su/uploads/posts/' . $dir_name . '/' . $pic_name . '" alt="Джейн Фэллон - Дважды два - четыре" title="' . $title . '" /><!--dle_image_end--></div><br />
			<div style="text-align:center;">' . nl2br(mb_substr($annotation, 0, 200)) . '</div>';

            $xfields = [];

            $xfields['litres_link'] = $litres_link;

            $book_type = $data['@attributes']['type'];

            if ($book_type == 0) {
                $xfields['hub_id'] = $book->getHubId();
            } elseif($book_type == 1) {
                $xfields['hub_id_audio'] = $book->getHubId();
            }

            $xfields_str = $this->implode_xfields($xfields);

            try {
                $local_id = $book->getLocalId();

                if ($local_id == -1) {
                    continue;
                }

                $dlePost = $this->em->getRepository(DlePost::class)->find($local_id);

                if (!$dlePost) {
                    $dlePost = new DlePost();
                    $dlePost->setDate(new \DateTime());
                    $dlePost->setAutor('litres');
                    $dlePost->setShortStory(mb_convert_encoding($short_story, 'UTF-8'));
                    $dlePost->setFullStory($full_story);
                    $dlePost->setXfields(mb_convert_encoding($xfields_str, 'UTF-8'));
                    $dlePost->setTitle($title);
                    $dlePost->setDescr('');
                    $dlePost->setKeywords('');
                    $dlePost->setCategory($local_categories);
                    $dlePost->setAltName($alt_name);
                    $dlePost->setCommNum(0);
                    $dlePost->setAllowComm(1);
                    $dlePost->setAllowMain(0);
                    $dlePost->setApprove(1);
                    $dlePost->setFixed(0);
                    $dlePost->setAllowBr(1);
                    $dlePost->setSymbol('');
                    $dlePost->setTags('');
                    $dlePost->setMetatitle($title);

                    $this->em->persist($dlePost);
                    $this->em->flush();

                    //update image
                    $local_id = $dlePost->getId();

                    $dleImages = new DleImages();
                    $dleImages->setImages($dir_name . '/' . $pic_name);
                    $dleImages->setNewsId($local_id);
                    $dleImages->setAuthor('litres');
                    $dleImages->setDate(time());

                    $this->em->persist($dleImages);
                    $this->em->flush();

                    $book->setLocalId($local_id);
                    $this->em->persist($book);
                    $this->em->flush();
                }

                if ($book->getNeedLocalUpdate()) {

                    $dlePost->setDate(new \DateTime());
                    $dlePost->setAutor('litres');
                    $dlePost->setShortStory(mb_convert_encoding($short_story, 'UTF-8'));
                    $dlePost->setFullStory($full_story);
                    $dlePost->setXfields(mb_convert_encoding($xfields_str, 'UTF-8'));
                    $dlePost->setTitle($title);
                    $dlePost->setDescr('');
                    $dlePost->setKeywords('');
                    $dlePost->setCategory($local_categories);
                    $dlePost->setAltName($alt_name);
                    $dlePost->setCommNum(0);
                    $dlePost->setAllowComm(1);
                    $dlePost->setAllowMain(0);
                    $dlePost->setApprove(1);
                    $dlePost->setFixed(0);
                    $dlePost->setAllowBr(1);
                    $dlePost->setSymbol('');
                    $dlePost->setTags('');
                    $dlePost->setMetatitle($title);

                    $this->em->persist($dlePost);
                    $this->em->flush();

                    //reset flag
                    $book->setNeedLocalUpdate(false);
                    $this->em->persist($book);
                    //$log->write('Update dle post id: ' . $local_id);

                    //update image
                    $local_id = $dlePost->getId();

                    $dleImages = new DleImages();
                    $dleImages->setImages($dir_name . '/' . $pic_name);
                    $dleImages->setNewsId($local_id);
                    $dleImages->setAuthor('litres');
                    $dleImages->setDate(time());

                    $this->em->persist($dleImages);
                    $this->em->flush();
                }



            } catch (\Exception $e) {
                print $e->getMessage() . "\n";

                if (!$this->em->isOpen()) {
                    $this->em = $this->em->create(
                        $this->em->getConnection(),
                        $this->em->getConfiguration()
                    );
                }
                $local_id = -1;

                $book->setNeedLocalUpdate(false);
                //$book->setLocalId($local_id);
                $this->em->persist($book);
                $this->em->flush();
                echo $book->getId();
                exit;
            }


//            try {
//                $book->setLocalId($local_id);
//                $this->em->persist($book);
//                $this->em->flush();
//
//
//            } catch (ORMException $e) {
//                $this->em = $this->em->create(
//                    $this->em->getConnection(),
//                    $this->em->getConfiguration()
//                );
//                $book->setLocalId($local_id);
//                $this->em->persist($book);
//                $this->em->flush();
//
//            }

            if ($local_id != -1) {
                $this->makeCover($data, $dir_name, $pic_name);
            }
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }

    /**
     * Get main author.
     *
     * @param $data
     * @return null
     */
    private function getMainAuthor($data)
    {
        echo count($data['authors']);

        if ( isset($data['authors']['author'][0]) ) {
            //print_r($data['authors']);
            $mainAuthor = null;
            $maxLvl = 0;
            foreach ($data['authors']['author'] as $author) {

                $lvl = intval($author['lvl']);
                if ($maxLvl < $lvl) {
                    $maxLvl = $lvl;
                    $mainAuthor = $author;
                }
            }

            return $mainAuthor;
        } else {
            return $data['authors']['author'];
        }
    }

    /**
     * Get annotation.
     *
     * @param $data
     * @return mixed
     */
    private function getAnnotation($data){

        if (isset($data['title-info']['annotation'])) {


            if (isset($data['title-info']['annotation']['p']) && is_array($data['title-info']['annotation']['p'])) {
                echo 's1';
                print_r($data['title-info']['annotation']);

                if (isset($data['title-info']['annotation']['p'][0]['emphasis'])) {

                    return $data['title-info']['annotation']['p'][0]['emphasis'];

                } elseif (isset($data['title-info']['annotation']['p']['emphasis'])) {

                    if (isset($data['title-info']['annotation']['p']['emphasis'][0])) {
                        return $data['title-info']['annotation']['p']['emphasis'][0];
                    }

                    return $data['title-info']['annotation']['p']['emphasis'];

                } elseif (isset($data['title-info']['annotation']['p'][0])) {

                    return $data['title-info']['annotation']['p'][0];

                }

            } elseif (isset($data['title-info']['annotation']['p'])) {

                if (isset($data['title-info']['annotation']['p']['emphasis'])) {
                    echo 's2';
                    print_r($data['title-info']['annotation']);
                    return $data['title-info']['annotation']['p']['emphasis'];
                } else {
                    echo 's3';
                    print_r($data['title-info']['annotation']);
                    return $data['title-info']['annotation']['p'];
                }

            } else {
                return '';
            }
        }
    }

    /**
     * Make cover.
     *
     * @param $data
     * @param $dir_name
     * @param $pic_name
     */
    private function makeCover($data, $dir_name, $pic_name)
    {
        @mkdir(getenv('ROOT_DIR') . '/uploads/posts/' . $dir_name . '/thumbs', 0777, true);

        $cover_id = $data['@attributes']['file_id'];

        while (strlen($cover_id) < 8){
            $cover_id = '0' . $cover_id;
        }

        if ($data['@attributes']['cover'] != '') {

            try {
                $cover_path = 'http://www.litres.ru/static/bookimages/' . $cover_id[0] . $cover_id[1] . '/' . $cover_id[2] . $cover_id[3] . '/' . $cover_id[4] . $cover_id[5] . '/' . $cover_id . '.bin.dir/' . $cover_id . '.cover.' . $data['@attributes']['cover'];

                $new_image = new Picture($cover_path);
                $new_image->imageresizewidth(240);

                $new_image->imagesave($new_image->image_type, getenv('ROOT_DIR') . '/uploads/posts/' . $dir_name . '/' . $pic_name, 85);
                $new_image->imageout();
            } catch (\Exception $e) {
                print $e->getMessage() . "\n";
            }
        }

    }

    private function totranslit($var, $lower = true, $punkt = true) {
        $NpjLettersFrom = "абвгдезиклмнопрстуфцыі";
        $NpjLettersTo = "abvgdeziklmnoprstufcyi";
        $NpjBiLetters = array ("й" => "j", "ё" => "yo", "ж" => "zh", "х" => "x", "ч" => "ch", "ш" => "sh", "щ" => "shh", "э" => "ye", "ю" => "yu", "я" => "ya", "ъ" => "", "ь" => "", "ї" => "yi", "є" => "ye" );

        $NpjCaps = "АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЬЪЫЭЮЯЇЄІ";
        $NpjSmall = "абвгдеёжзийклмнопрстуфхцчшщьъыэюяїєі";

        $var = str_replace( ".php", "", $var );
        $var = trim( strip_tags( $var ) );
        $var = preg_replace( "/\s+/ms", "-", $var );
        $var = strtr( $var, $NpjCaps, $NpjSmall );
        $var = strtr( $var, $NpjLettersFrom, $NpjLettersTo );
        $var = strtr( $var, $NpjBiLetters );

        if ( $punkt ) $var = preg_replace( "/[^a-z0-9\_\-.]+/mi", "", $var );
        else $var = preg_replace( "/[^a-z0-9\_\-]+/mi", "", $var );

        $var = preg_replace( '#[\-]+#i', '-', $var );

        if ( $lower ) $var = strtolower( $var );

        if( strlen( $var ) > 50 ) {

            $var = substr( $var, 0, 50 );

            if( ($temp_max = strrpos( $var, '-' )) ) $var = substr( $var, 0, $temp_max );

        }

        return $var;
    }

    private function explode_xfields($data){
        //преобразует строку xfields в двумерный массив
        $xfields_array = explode('||',$data);
        foreach ($xfields_array as $xfield){
            $xfield_array = explode('|',$xfield);
            $xfields[$xfield_array[0]] = $xfield_array[1];
        }
        return is_array($xfields) ? $xfields : false;
    }

    private  function implode_xfields($data){
        //преобразует двумерный массив в строку xfields
        foreach ($data as $key => $value){
            $xfields[] = $key . '|' . $value;
        }

        if (!is_array($xfields)) return false;

        $xfields_string = implode('||',$xfields);

        return $xfields_string;
    }
}
