<?php

namespace DirectoryPlatform\FrontBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Payum\Core\Request\GetHumanStatus;

use DirectoryPlatform\AppBundle\Entity\Order;
use DirectoryPlatform\AppBundle\Entity\OrderItem;
use DirectoryPlatform\AppBundle\Entity\Payment;
use DirectoryPlatform\FrontBundle\Form\Type\OrderType;
use Symfony\Component\HttpFoundation\JsonResponse;
use DateTime;

class OrderController extends Controller
{
    /**
     * @Route("/accounts/orders", name="order")
     */
    public function ordersAction(Request $request)
    {
        $orders = $this->getDoctrine()->getRepository('AppBundle:Order')->findAllByUser($this->getUser());
        $orders = $this->get('knp_paginator')->paginate($orders, $request->query->getInt('page', 1), 10);

        return $this->render('FrontBundle::Order/index.html.twig', [
            'orders' => $orders
        ]);
    }

    /**
     * @Route("/accounts/invoice/{id}", name="invoice", requirements={"id": "\d+"})
     * @ParamConverter("order", class="DirectoryPlatform\AppBundle\Entity\Order")
     */
    public function invoiceAction(Request $request, Order $order)
    {
        if ($order->getStatus() != Order::STATUS_COMPLETED) {
            throw $this->createAccessDeniedException('You are not allowed to access this page.');
        }

        return $this->render('FrontBundle::Order/invoice.html.twig', [
            'order' => $order,
            'orderRepository' => $this->getDoctrine()->getRepository('AppBundle:Order'),            
        ]);
    }

    /**
     * @Route("/accounts/order/paypal/completed", name="order_paypal_completed")
     */
    public function orderPaypalCompletedAction(Request $request)
    {
        $token = $this->get('payum')->getHttpRequestVerifier()->verify($request);

        $identity = $token->getDetails();
        $payment = $this->get('payum')->getStorage($identity->getClass())->find($identity);

        $gateway = $this->get('payum')->getGateway($token->getGatewayName());
        $gateway->execute($status = new GetHumanStatus($payment));

        $order = $payment->getOrder();

        if ($status->isCaptured() || $status->isAuthorized()) {
            $order->setStatus(Order::STATUS_COMPLETED);
            $this->addFlash('success', $this->get('translator')->trans('Payment has been successful.'));
        }

        if ($status->isPending()) {
            $this->addFlash('danger', $this->get('translator')->trans('Payment has been canceled.'));
        }

        if ($status->isFailed() || $status->isCanceled()) {
            $order->setStatus(Order::STATUS_CANCELED);
            $this->addFlash('danger', $this->get('translator')->trans('Payment has been canceled.'));
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($order);
            $em->flush();
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->get('translator')->trans('An error occurred when saving object.'));
        }


        return $this->redirectToRoute('order');
    }

    /**
     * @Route("/accounts/checkout", name="checkout")
     */
    public function checkoutAction(Request $request)
    {
        $orderForm = $this->createForm(OrderType::class, null, [
            'user' => $this->getUser(),
            'gateways' => $this->getParameter('app.gateways'),
        ]);
        $orderForm->handleRequest($request);

        // Calculate total price
        $products = $request->getSession()->get('products');
        if (!$products) {
            $this->addFlash('danger', $this->get('translator')->trans('Cart is empty. Not able to proceed checkout.'));

            return $this->redirectToRoute('cart');
        }

        $price = 0;
        foreach ($products as $product) {
            $price += $product['price'];
        }

        // Save order
        if ($orderForm->isSubmitted() && $orderForm->isValid()) {
            $order = $orderForm->getData();
            $order->setStatus(Order::STATUS_NEW);
            $order->setUser($this->getUser());
            $order->setCurrency($this->getParameter('app.currency'));
            $order->setPrice($price);

            try {
                $em = $this->getDoctrine()->getManager();
                $em->persist($order);
                $em->flush();
            } catch (\Exception $e) {
                $this->addFlash('danger', $this->get('translator')->trans('An error occurred whe saving order.'));
            }

            // Save order items
            foreach ($products as $product) {
                $listing = $this->getDoctrine()->getRepository('AppBundle:Listing')->findOneBy(['id' => $product['listing_id']]);

                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setPrice($product['price']);
                $orderItem->setType($product['type']);
                $orderItem->setListing($listing);
                $orderItem->setDuration($product['duration']);

                try {
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($orderItem);
                    $em->flush();
                } catch (\Exception $e) {
                    $this->addFlash('danger', $this->get('translator')->trans('An error occurred whe saving order item.'));
                }
            }

            $request->getSession()->remove('products');
            $this->addFlash('success', $this->get('translator')->trans('Order has been successfully saved.'));

            if ($order->getGateway() == 'paypal') {
                $gatewayName = 'paypal_express_checkout';

                $storage = $this->get('payum')->getStorage(Payment::class);

                $payment = $storage->create();
                $payment->setNumber(uniqid());
                $payment->setCurrencyCode($this->getParameter('app.currency'));
                $payment->setTotalAmount($price * 100);
                $payment->setDescription('A description');
                $payment->setClientId($order->getCompany()->getId());
                $payment->setClientEmail($order->getCompany()->getEmail());
                $payment->setOrder($order);

                $storage->update($payment);

                $captureToken = $this->get('payum')->getTokenFactory()->createCaptureToken($gatewayName, $payment, 'order_paypal_completed');
                return $this->redirect($captureToken->getTargetUrl());
            }

            return $this->redirectToRoute('order');
        }

        $stripePublicKey = $this->container->getParameter('stripe_public_key');

        return $this->render('FrontBundle::Order/checkout.html.twig', [
            'order'           => $orderForm->createView(),
            'stripePublicKey' => $stripePublicKey,
        ]);
    }

    /**
     * @Route("/stripe-popup", name="stripe_popup")
     */
    public function stripePopupAction(Request $request)
    {
        $formData = $request->request->all();
        $request->getSession()->set('getway', $formData['order']['gateway']);
        $request->getSession()->set('companyName', $formData['order']['name']);
        $request->getSession()->set('regNo', $formData['order']['regNo']);
        $request->getSession()->set('vatNo', $formData['order']['vatNo']);
        $request->getSession()->set('country', $formData['order']['country']);
        $request->getSession()->set('county', $formData['order']['county']);
        $request->getSession()->set('city', $formData['order']['city']);
        $request->getSession()->set('street', $formData['order']['street']);
        $request->getSession()->set('postalCode', $formData['order']['postalCode']);
        $request->getSession()->set('_token', $formData['order']['_token']);
        

        $result['tabconent'] = $this->renderView('FrontBundle::Order/stripePopup.html.twig');

        $result['status'] = 'success';

        return new JsonResponse( $result );
    }

    /**
     * @Route("/order-stripe-payment-process", name="order_stripe_payment_process")
     */
    public function stripePaymentAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $paymentStatus = array();
        $data['stripeToken'] =  $request->get('stripeToken');
        $getway = $request->getSession()->get('getway');
        $name = $request->getSession()->get('companyName');
        $regNo = $request->getSession()->get('regNo');
        $vatNo = $request->getSession()->get('vatNo');
        $country = $request->getSession()->get('country');
        $county = $request->getSession()->get('county');
        $city = $request->getSession()->get('city');
        $street = $request->getSession()->get('street');
        $postalCode = $request->getSession()->get('postalCode');

        // Calculate total price
        $products = $request->getSession()->get('products');

        $price = 0;
        foreach ($products as $product) {
            $price += $product['price'];
        }

        try
        {
            $objUser = $this->getUser();
            $newUserId = $objUser->getId();
            $newUserName = $objUser->getUserName();
            $newUserEmail = $objUser->getEmail();

            $stripePrivateKey = $this->container->getParameter('stripe_secret_key');

            \Stripe\Stripe::setApiKey($stripePrivateKey);

            // Create customer
            $customer = \Stripe\Customer::create(array(
              "source" => $data['stripeToken'],
              "description" => $newUserName."(".$newUserId.")",
              "email" => $newUserEmail
            ));

            $intStripeCustomerId = $customer->id;
            $strDefaultSourceId = $customer->default_source;

            $request->getSession()->set('stripe_customer_id', $intStripeCustomerId);

            //charge to customer
            $charge = \Stripe\Charge::create(array(
                "amount" => ($price*100), // amount in cents, again
                "currency" => "usd",
                "customer" => $intStripeCustomerId,
                "source" => $strDefaultSourceId
                )
            );

            if ($charge->paid == true)
            {
                $charge->StatusID = 0;
                $charge->Status = $charge->status = 'APPROVED';
                $charge->ResponseMessage = "Successfully done.";

                $expiryMonth = $charge->source['exp_month'];
                $expiryYear = $charge->source['exp_year'];
                $expiryDate = new Datetime($expiryYear.'-'.$expiryMonth.'-01');

                $datediff = strtotime($expiryDate->format('Y-m-d')) - strtotime(date('Y-m-d'));
                $no_of_days = round(($datediff / (60 * 60 * 24)));
            }
            else
            {
                $charge->StatusID = 1;
                $charge->Status = $charge->status = 'FAILED';

                if(isset($charge->failure_message))
                {
                    $charge->ResponseMessage = $charge->failure_message;
                }
            }

            $charge->PostedDate = date("Y-m-d",$charge->created);
            $charge->TransactionID = $charge->id;
            $charge->Message = $charge->ResponseMessage;

            
        }
        catch (\Stripe\Error\Card $e)
        {
            // Card was declined.
            $e_json = $e->getJsonBody();
            $err = $e_json['error'];

            $charge = new \stdClass();
            $charge->paid = false;
            $charge->id = '';
            $charge->created = time();
            $charge->StatusID = 1;
            $charge->Status = $charge->status = 'FAILED';
            $charge->ResponseMessage = $err['message']. "(".$err['code'].")";

            // if(isset($err['decline_code']))
            // {
            //  $charge->ResponseMessage .= " - ".$err['decline_code'];
            // }

            $charge->error_response = $err;
            
        }
        catch (\Stripe\Error\ApiConnection $e)
        {
            $e_json = $e->getJsonBody();
            $err = $e_json['error'];

            // Network problem, perhaps try again.
            $charge = new \stdClass();
            $charge->paid = false;
            $charge->id = '';
            $charge->created = time();
            $charge->StatusID = 1;
            $charge->Status = $charge->status = 'FAILED';
            $charge->ResponseMessage = "Network problem, perhaps try again.";
            $charge->error_response = $err;
            

        } catch (\Stripe\Error\InvalidRequest $e)
        {
            $e_json = $e->getJsonBody();
            $err = $e_json['error'];

            // You screwed up in your programming. Shouldn't happen!
            $charge = new \stdClass();
            $charge->paid = false;
            $charge->id = '';
            $charge->created = time();
            $charge->StatusID = 1;
            $charge->Status = $charge->status = 'FAILED';
            $charge->ResponseMessage = $err['message'];
            // if(isset($charge->failure_message))
            // {
            //  $charge->ResponseMessage = $charge->failure_message;
            // }
            $charge->error_response = $err;
            

        } catch (\Stripe\Error\Api $e)
        {
            $e_json = $e->getJsonBody();
            $err = $e_json['error'];

            // Stripe's servers are down!
            $charge = new \stdClass();
            $charge->paid = false;
            $charge->id = '';
            $charge->created = time();
            $charge->StatusID = 1;
            $charge->Status = $charge->status = 'FAILED';
            $charge->ResponseMessage = "Stripe's servers are down!";
            $charge->error_response = $err;
            

        }
        catch (\Stripe\Error\Base $e)
        {
            $e_json = $e->getJsonBody();
            $err = $e_json['error'];

            //Something else that's not the customer's fault.
            $charge = new \stdClass();
            $charge->paid = false;
            $charge->id = '';
            $charge->created = time();
            $charge->StatusID = 1;
            $charge->Status = $charge->status = 'FAILED';
            $charge->ResponseMessage = "Something went wrong!";
            $charge->error_response = $err;
            
        }

        $paymentStatus['status'] = $charge->paid == true  ? 'success' : 'failed';
        $paymentStatus['msg'] = $charge->ResponseMessage;

        if( $paymentStatus['status'] == 'success' )
        {
            $stripe_customer_id = $request->getSession()->get('stripe_customer_id');

            $user = $this->getUser();
            $user->setStripeCustomerId( $stripe_customer_id );
            $em->persist($user);
            $em->flush();

            $order = new Order();
            $order->setStatus(Order::STATUS_NEW);
            $order->setUser($this->getUser());
            $order->setCurrency($this->getParameter('app.currency'));
            $order->setPrice($price);
            $order->setGateway( $getway );
            $order->setName( $name );
            $order->setRegNo( $regNo );
            $order->setVatNo( $vatNo );
            $order->setCountry( $country );
            $order->setCounty( $county );
            $order->setCity( $city );
            $order->setStreet( $street );
            $order->setPostalCode( $postalCode );
            $em->persist($order);
            $em->flush();


            // Save order items
            foreach ($products as $product) {
                $listing = $this->getDoctrine()->getRepository('AppBundle:Listing')->findOneBy(['id' => $product['listing_id']]);

                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setPrice($product['price']);
                $orderItem->setType($product['type']);
                $orderItem->setListing($listing);
                $orderItem->setDuration($product['duration']);
                $em->persist($orderItem);
            }
            $em->flush();

            $request->getSession()->remove('getway');
            $request->getSession()->remove('companyName');
            $request->getSession()->remove('regNo');
            $request->getSession()->remove('vatNo');
            $request->getSession()->remove('country');
            $request->getSession()->remove('county');
            $request->getSession()->remove('city');
            $request->getSession()->remove('street');
            $request->getSession()->remove('postalCode');
            $request->getSession()->remove('_token');
            $request->getSession()->remove('products');

            $paymentStatus['status'] = 'success';
            $paymentStatus['msg'] = $charge->ResponseMessage;

            $this->addFlash('success', $this->get('translator')->trans($charge->ResponseMessage));
        }
        else
        {
            $paymentStatus['status'] = 'failed';
            $paymentStatus['msg']   = $charge->ResponseMessage;
        }

        $response = new JsonResponse($paymentStatus);

        return $response;
    }
}