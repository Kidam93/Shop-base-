<?php
namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\AdminRepository;
use App\Repository\ArticleRepository;
use App\Controller\Login\LoginRequest;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Subscribe\SubscribeBDD;
use App\Controller\Subscribe\SubmitedValid;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\Subscribe\SubscribeRequest;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class baseController extends AbstractController{

    const NB_ART = 3;

    private $articleRepo;

    private $adminRepo;

    private $userRepo;

    private $em;

    protected $requestStack;

    private $session;

    public function __construct(ArticleRepository $articleRepo,
                                AdminRepository $adminRepo,
                                UserRepository $userRepo,
                                EntityManagerInterface $em,
                                RequestStack $requestStack,
                                SessionInterface $session){
        $this->articleRepo = $articleRepo;
        $this->adminRepo = $adminRepo;
        $this->userRepo = $userRepo;
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->session = $session;
    }

    /**
     * @Route("", name="home")
     */
    public function home(){
        // DEFAULT PAGE
        $id = 1;
        
        return $this->redirectToRoute("home.pages", [
            'id' => $id
        ]);
    }

    /**
     * @Route("/pages-{id}", name="home.pages")
     */
    public function homepage($id, PaginatorInterface $paginator, Request $request){

        $all = $this->articleRepo->findAll();
        $nbPages = ceil(sizeof($all) / self::NB_ART);
        $search = $this->requestStack->getCurrentRequest()->request->get('search');
        
        if(empty($search)){
            $nbPages = ceil(sizeof($all) / self::NB_ART);
            $visible = $this->articleRepo->findNbArticles(self::NB_ART, (int)$id);
        }
        if(!empty($search)){
            $nbPages = null;
            $visible = $this->articleRepo->findSearch($search);
        }
        // VIRTUAL BASKET
        // session_start();
        // dd($_SESSION['panier']);
        // 
        return $this->render("home/home.html.twig", [
            'articles' => $visible,
            'id' => $this->session->get('user_id'),
            'nbmax' => $nbPages,
            'search' => $id,
        ]);
    }

    /**
     * @Route("/subscribe", name="home.subscribe")
     */
    public function subscribe(){
        if(!empty($_POST)){
            $subscribeRequest = new SubscribeRequest($this->requestStack);
            $dataValid = new SubmitedValid($subscribeRequest->getLastname(),
                                            $subscribeRequest->getFirstname(), 
                                            $subscribeRequest->getUsername(), 
                                            $subscribeRequest->getEmail(),  
                                            $subscribeRequest->getPassword(), 
                                            $subscribeRequest->getConfirmed());
            $username = $this->userRepo->loginUser($subscribeRequest->getUsername());
            // dd($username);
            if(empty($username)){
                if($dataValid->isValid() === true){
                    $subscribeBDD = new SubscribeBDD($subscribeRequest->getLastname(),
                                                        $subscribeRequest->getFirstname(), 
                                                        $subscribeRequest->getUsername(), 
                                                        $subscribeRequest->getEmail(),  
                                                        $subscribeRequest->getPassword());
                    // $subscribeBDD->insert($this->em) return array $data
                    $subscribeBDD->sendMailToken($subscribeBDD->insert($this->em));
                }else{
                    $dataValid->getErrors();
                }
            }else{
                dd("Ce pseudo est deja pris");
                // $this->addFlash('danger', "Ce pseudo est deja pris");
            }
        }
        return $this->render("home/subscribe.html.twig");
    }

    /**
     * @Route("/login", name="home.login")
     */
    public function login(){

        // // // // //
        //          //
        //   HERE   //
        //          //
        // // // // //

        $loginRequest = new LoginRequest($this->requestStack);
        // username -> unique
        $login = $loginRequest->getUsername();
        // 
        $password = $loginRequest->getPassword();
        $result = $this->userRepo->loginUser($login);
        if(!empty($result)){
            $passwordBDD = $result[0]['password'];
            $password = password_verify($password, $passwordBDD);
            if($password === true){
                $idBDD = (int)$result[0]['id'];
                $this->session->set('user_id', $idBDD);
                // dd($this->session->get('user_id'));
                return $this->redirectToRoute("home");
            }
        }
        
        return $this->render("home/login.html.twig");
    }

    /**
     * @Route("/disconnected", name="home.disconnected")
     */
    public function disconnected(){
        if(!is_null($this->session->get('user_id'))){
            $this->session->set('user_id', null);
        }
        // dd($this->session->get('user_id'));
        return $this->redirectToRoute("home");
    }

    /**
     * @Route("/user/id={id}/token={token}", name="home.verify")
     */
    public function verify($id, $token){
        $user = $this->userRepo->find($id);
        $userId = null;
        $userToken = null;
        if(!empty($user)){
            $userId = $user->getId();
            $userToken = $user->getToken();
        }
        if((int)$id === $userId && $token === $userToken){
            $this->session->set('user_id', $userId);
            return $this->redirectToRoute("home");
        }else{
            return $this->redirectToRoute("home.subscribe");
        }
    }
}