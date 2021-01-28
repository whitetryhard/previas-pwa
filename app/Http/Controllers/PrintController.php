<?php

namespace App\Http\Controllers;

use App\Escpos;
use App\Order;
use Illuminate\Http\Request;
use Mike42\Escpos\Printer;

class PrintController extends Controller
{
    //48 for 8mm, 30 for 5mm
    public $char_per_line = 48;

    public function printInvoice($order_id)
    {
        $main = new Escpos();
        $main->load('network', '192.168.1.2');

        $order = Order::where('unique_order_id', $order_id)->firstOrFail();

        $store_name = $order->restaurant->name;
        $store_address = $order->restaurant->address;
        $order_id = $order->unique_order_id;

        //init store header
        $main->printer->setJustification(Printer::JUSTIFY_CENTER);

        $main->printer->feed();
        $main->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
        $main->printer->text(config('settings.storeName'));
        $main->printer->selectPrintMode();
        $main->printer->feed();

        $main->printer->selectPrintMode(Printer::MODE_FONT_B);
        $main->printer->text('This is the subtitle');
        $main->printer->selectPrintMode();
        $main->printer->feed();

        $main->printer->text($this->drawLine());
        $main->printer->feed();

        $main->printer->feed();
        $main->printer->setEmphasis(true);
        $main->printer->setUnderline(1);
        $main->printer->text($store_name);
        $main->printer->setUnderline(0);
        $main->printer->setEmphasis(false);
        $main->printer->feed();
        $main->printer->text($store_address);
        $main->printer->feed();

        $main->printer->text('Order ID: ' . $order_id);
        $main->printer->feed();

        $main->printer->text('Ordered Date: ' . $order->created_at->format('Y-m-d h:i A'));
        $main->printer->feed(2);

        $main->printer->setJustification();

        $main->printer->setJustification(Printer::JUSTIFY_LEFT);

        //bill item header
        $main->printer->text($this->drawLine());
        $string = $this->columnify($this->columnify($this->columnify('QTY', ' ' . 'ITEM', 10, 40, 0, 0), 'Price', 50, 25, 0, 0), ' ' . 'TOTAL', 75, 25, 0, 0);
        $main->printer->setEmphasis(true);
        $main->printer->text(rtrim($string));
        $main->printer->feed();
        $main->printer->setEmphasis(false);
        $main->printer->text($this->drawLine());

        foreach ($order->orderitems as $orderitem) {

            //calculating item total
            $itemTotal = ($orderitem->price + $this->calculateAddonTotal($orderitem->order_item_addons)) * $orderitem->quantity;

            //get addons and add to orderitem->addon_name
            $orderItemAddons = count($orderitem->order_item_addons);
            if ($orderItemAddons > 0) {
                $addons = '';
                foreach ($orderitem->order_item_addons as $addon) {
                    $addons .= $addon->addon_name . ', ';
                }
                $addons = rtrim($addons, ', ');
                $orderitem->addon_name = $addons;
            }

            //print products/items
            if ($orderItemAddons > 0) {
                $string = rtrim($this->columnify($this->columnify($this->columnify($orderitem->quantity, $orderitem->name . ' (' . $orderitem->addon_name . ')', 10, 40, 0, 0), $orderitem->price, 50, 25, 0, 0), $itemTotal, 75, 25, 0, 0));
            } else {
                $string = rtrim($this->columnify($this->columnify($this->columnify($orderitem->quantity, $orderitem->name, 10, 40, 0, 0), $orderitem->price, 50, 25, 0, 0), $itemTotal, 75, 25, 0, 0));
            }

            $main->printer->text($string);
            $main->printer->feed(1);

        }

        $main->printer->feed();
        $main->printer->text($this->drawLine());

        $main->printer->setJustification(Printer::JUSTIFY_LEFT);

        //coupon
        if ($order->coupon_name != null) {
            $coupon = $this->columnify('Coupon: ', $order->coupon_name, 75, 25, 0, 0);
            $main->printer->text(rtrim($coupon));
            $main->printer->feed();
        }

        //store charge
        $storeCharge = $this->columnify('Store Charge: ', $order->restaurant_charge, 75, 25, 0, 0);
        $main->printer->text(rtrim($storeCharge));
        $main->printer->feed();

        //delivery charge
        $deliveryCharge = $this->columnify('Delivery Charge: ', $order->delivery_charge, 75, 25, 0, 0);
        $main->printer->text(rtrim($deliveryCharge));
        $main->printer->feed();

        //Tax
        if ($order->tax != null) {
            $tax = $this->columnify('Tax: ', $order->tax . '%', 75, 25, 0, 0);
            $main->printer->text(rtrim($tax));
            $main->printer->feed();
        }

        //Order Total

        $main->printer->setJustification(Printer::JUSTIFY_CENTER);
        $main->printer->text($this->drawLine());
        $main->printer->setJustification();

        $orderTotal = $this->columnify('Total: ', $order->total, 75, 25, 0, 0);
        $main->printer->setEmphasis(true);
        $main->printer->text(rtrim($orderTotal));
        $main->printer->setEmphasis(false);
        $main->printer->feed();

        $main->printer->setJustification();

        //test
        // $main->printer->feed();
        // $text = str_pad('SAURABH', 10, ' ', STR_PAD_LEFT);
        // $main->printer->text($text);
        // $main->printer->feed();

        $main->printer->setJustification(Printer::JUSTIFY_CENTER);
        $main->printer->text($this->drawLine());
        $main->printer->setJustification();

        $main->printer->setJustification(Printer::JUSTIFY_CENTER);
        $main->printer->feed();
        $main->printer->text('Thank you!!!');
        $main->printer->feed(2);
        $main->printer->setJustification();
        //cut and close connection for print
        $main->printer->cut();
        $main->printer->close();

        echo 'DONE';
        die();
    }

    public function printKitchenReceipt($order_id)
    {
        # code...
    }

    public function drawLine()
    {
        $new = '';
        for ($i = 1; $i < $this->char_per_line; $i++) {
            $new .= '-';
        }
        return $new . "\n";
    }

    public function calculateAddonTotal($addons)
    {
        $total = 0;
        foreach ($addons as $addon) {
            $total += $addon->addon_price;
        }
        return $total;
    }

    public function columnify($leftCol, $rightCol, $leftWidthPercent, $rightWidthPercent, $space = 2, $remove_for_space = 0)
    {
        $char_per_line = $this->char_per_line - $remove_for_space;

        $leftWidth = $char_per_line * $leftWidthPercent / 100;
        $rightWidth = $char_per_line * $rightWidthPercent / 100;

        $leftWrapped = wordwrap($leftCol, $leftWidth, "\n", true);
        $rightWrapped = wordwrap($rightCol, $rightWidth, "\n", true);

        $leftLines = explode("\n", $leftWrapped);
        $rightLines = explode("\n", $rightWrapped);
        $allLines = array();
        for ($i = 0; $i < max(count($leftLines), count($rightLines)); $i++) {
            $leftPart = str_pad(isset($leftLines[$i]) ? $leftLines[$i] : '', $leftWidth, ' ');
            $rightPart = str_pad(isset($rightLines[$i]) ? $rightLines[$i] : '', $rightWidth, ' ');
            $allLines[] = $leftPart . str_repeat(' ', $space) . $rightPart;
        }
        return implode($allLines, "\n") . "\n";
    }
}
