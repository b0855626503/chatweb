<?php

namespace Gametech\Wallet\Http\Controllers;


use Gametech\Member\Repositories\MemberRepository;
use Gametech\Promotion\Repositories\PromotionContentRepository;
use Gametech\Promotion\Repositories\PromotionRepository;


class PromotionController extends AppBaseController
{
    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    private $promotionRepository;

    private $proContentRepository;

    private $memberRepository;

    /**
     * Create a new Repository instance.
     *
     * @param PromotionRepository $promotionRepo
     * @param MemberRepository $memberRepo
     * @param PromotionContentRepository $proContentRepo
     */
    public function __construct
    (
        PromotionRepository $promotionRepo,
        MemberRepository $memberRepo,
        PromotionContentRepository $proContentRepo
    )
    {
        $this->middleware('customer');

        $this->_config = request('_config');

        $this->promotionRepository = $promotionRepo;

        $this->memberRepository = $memberRepo;

        $this->proContentRepository = $proContentRepo;
    }

    public function index()
    {
        $promotions = $this->promotionRepository->findWhere(['enable' => 'Y' , 'use_wallet' => 'Y',  ['code','<>',0]]);
        $pro_contents = $this->proContentRepository->findWhere(['enable' => 'Y',  ['code','<>',0]]);

        return view($this->_config['view'], compact('promotions','pro_contents'));
    }




}
