<?php

function create_edit_pickup_form()
{
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (empty($APIKey)) {
        echo "Please, add your API key";
    }

    $url = 'https://app.bosta.co/api/v0/pickup-locations ';
    $result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
    ));

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
        echo "Something went wrong: $error_message";
    } else {
        $result = json_decode($result['body'])->data;

    }
    if ($_GET['pickupId'] && !empty($_GET['pickupId'])) {
        $url = 'https://app.bosta.co/api/v0/pickups/' . $_GET['pickupId'];
        $pickupEditData = wp_remote_get($url, array(
            'timeout' => 30,
            'method' => 'GET',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
        ));
        if (is_wp_error($pickupEditData)) {
            $error_message = $pickupEditData->get_error_message();
            echo "Something went wrong: $error_message";
        } else {
            $pickupEditData = json_decode($pickupEditData['body'])->message;
        }
    }
    echo "
              <style>
              table {
              border-collapse: collapse;
              width: 95%;
              }
              tr{
                  display:flex;
              }
              td{
               display: flex;
               flex-direction: column;
              }
              th, td {
              padding: 8px;
              }

              label{
                  font-weight:500;
                  font-size:15px;
                  padding-bottom:10px;
              }

              form {
                  margin-bottom: 30px;
              }

              .data{
                  height:32px;
                  width: 25vw;
              }

              .error{
                  text-align:center;
                  color:red;
              }

              .updated{
                  text-align:center;
                  color:green;
              }

              </style>";
    if ($_GET['pickupId']) {

        echo "<p class='create-pickup-title'>Edit Pickup";

    } else {
        echo "<p class='create-pickup-title'>Create Pickup";
    }
    ;
    echo "
            </p>
              <p class='create-pickup-subtitle'>  Setup the pickup info</p>
              <form method='post' action='#'>
              <table>
              <thead>
              </thead>
                  <tbody>
                  <tr>
                  <td>
                   <label >Pickup Location</label>
                   <select require class='data' name='businessLocationId' > ";
    for ($i = 0; $i < count($result); $i++) {
        $pickupLocation = $result[$i]->locationName;
        $id = $result[$i]->_id;
        if ($id == $pickupEditData->businessLocationId) {
            echo "<option value='$id' selected>$pickupLocation</option>";
        } else {
            echo "<option value='$id' >$pickupLocation</option>";
        }

    }
    function selectdCheck($value1, $value2)
    {
        if ($value1 == $value2) {
            echo 'selected="selected"';
        } else {
            echo '';
        }
        return;
    }
    echo " </select > </td>
               </tr>
                      <tr>
                         <td>
                          <label>Pickup Date</label>";
    if ($pickupEditData->scheduledDate) {

        $date = date('Y-m-d', strtotime($pickupEditData->scheduledDate));
        ?>
                            <input value = "<?php echo ($date); ?>"  require class='data' name='scheduledDate'  type='date'  />
                            <?php
} else {
        echo " <input require class='data' name='scheduledDate'  type='date' ";
    }

    echo "    </td>
                      </tr>
                      <tr>
                         <td>
                              <label >Pickup Time</label>
                              "
    ?>
<select require class='data' name='scheduledTimeSlot' >
   <option  <?php selectdCheck($pickupEditData->scheduledTimeSlot, '10:00 to 13:00');?>   value='10:00 to 13:00'>10:00AM to 01:00PM</option>
   <option <?php selectdCheck($pickupEditData->scheduledTimeSlot, '13:00 to 16:00');?>value='13:00 to 16:00' >01:00PM to 04:00PM</option>
</select>
<?php
echo "   </td>
                </tr>
                  </tbody>
              </table>";
    if ($_GET['pickupId']) {

        echo "<input class='primary-button' type='submit' name='create' value='Edit pickup'/>";

    } else {
        echo " <input class='primary-button' type='submit' name='create' value='Create pickup'/>";
    }
    echo " </form>
              <span class='pickup-location-note'>To create and edit pickup locations, and select the default pickup location <a class='pickup-location-link' href='https://business.bosta.co/settings/pickup-locations' target='_blank'>Click here</a> </span>
              <p class='pickup-location-note'> Is this a recurring pickup? If yes  <a class='pickup-location-link' href='https://stg-business.bosta.co/pickups/create' target='_blank'>Create from here</a></span>
              ";

    if (isset($_POST['create'])) {

        if (empty($_POST['scheduledDate'])) {
            echo "<div class='error'>Pickup Date is Required</div>";
        }

        if (empty($_POST['scheduledTimeSlot'])) {
            echo "<div class='error'>Pickup Time is Required<div>";
        }

        if (!empty($_POST['scheduledDate']) && !empty($_POST['scheduledTimeSlot'])) {
            create_pickup_action('create_pickup', $_POST['scheduledDate'], $_POST['scheduledTimeSlot'], $_POST['businessLocationId']);
        }
    }
}

function create_pickup_action($action, $scheduledDate, $scheduledTimeSlot, $businessLocationId)
{
    if ($action != 'create_pickup') {
        return;
    }

    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (empty($APIKey)) {
        $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
        wp_redirect($redirect_url);
    }
    $pickupData = new stdClass();
    $pickupData->scheduledDate = $scheduledDate;
    $pickupData->scheduledTimeSlot = $scheduledTimeSlot;
    $pickupData->businessLocationId = $businessLocationId;
    if ($_GET['pickupId']) {
        $result = wp_remote_request('https://app.bosta.co/api/v0/pickups/' . $_GET['pickupId'], array(
            'timeout' => 30,
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
            'body' => json_encode($pickupData),
        ));

        $result = json_decode($result['body']);
        if ($result->success === false) {
            echo "<div class='error'>" . $result->message . "<div>";
        } else {
            echo "<div class='updated'>Pickup Request Updated  Successfuly with id: " . $_GET['pickupId'] . "<div>";
        }
    } else {

        $result = wp_remote_post('https://app.bosta.co/api/v0/pickups', array(
            'timeout' => 30,
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
            'body' => json_encode($pickupData),
        ));

        $result = json_decode($result['body']);
        if ($result->success === false) {
            echo "<div class='error'>" . $result->message . "<div>";
        } else {
            echo "<div class='updated'>Pickup Request Created Successfuly with id: " . $result
                ->message->_id . "<div>";
            $redirect_url = admin_url('admin.php?') . 'page=bosta-woocommerce-view-pickups';
            wp_redirect($redirect_url);
        }
    }

}

function view_scheduled_pickups()
{
    global $pagenow, $typenow;
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (empty($APIKey)) {
        $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
        wp_redirect($redirect_url);
    }

    if ('admin.php' === $pagenow && $_GET['page'] = 'bosta-woocommerce-view-pickups' && $_GET['pickupId']) {

        echo "<p class='create-pickup-title'>Pickup Info
            </p>
              <p class='create-pickup-subtitle'>View all data about pickup

              </p>";
        $url = 'https://app.bosta.co/api/v0/pickups/' . $_GET['pickupId'];
        $result = wp_remote_post($url, array(
            'timeout' => 30,
            'method' => 'GET',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
            'body' => json_encode($pickupData),
        ));

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            echo "Something went wrong: $error_message";
        } else {
            $pickupInfo = json_decode($result['body'])->message;
            $pickupLocation = $pickupInfo->business->address->firstLine;
            $pickupLocationCity = $pickupInfo->business->address->city->name;
            $pickupLocationZone = $pickupInfo->business->address->zone->name;
            $pickupLocationDistrict = $pickupInfo->business->address->district->name;
            $noOfPackgaes = $pickupInfo->noOfPackages ? $pickupInfo->noOfPackages : "0";
            $contactPerson = $pickupInfo->contactPerson;
            $scheduledDate = date('d/m/Y', strtotime($pickupInfo->scheduledDate));
            $deliveries = $pickupInfo->deliveryTrackingNumbers ? count($pickupInfo->deliveryTrackingNumbers) : "0";
            echo "<table class='order-details-table pickup-info-table'>
          <tr>
             <th>Pickup Id </th>
             <th>Pickup location</th>
             <th>Pickup date</th>
             <th>Picked PCKGs</th>
             <th>Contact person</th>
          </tr>
          <tr>
             <td>$pickupInfo->puid</td>
             <td> $pickupLocation <br/>  $pickupLocationDistrict -  $pickupLocationZone,  $pickupLocationCity</td>
             <td>$scheduledDate</td>
             <td>  $noOfPackgaes</td>
             <td>$contactPerson->name <br/>$contactPerson->phone</td>
          </tr>
          <tr>
          <th>Notes </th>
          <th>Pickup Type</th>
          <th>Signature</th>
       </tr>
       <tr>
       <td class='last-field'>$pickupInfo->notes</td>
       <td class='last-field'>$pickupInfo->packageType</td>
       <td class='last-field'> <img  class='signature-image' src='$pickupInfo->signature'/></td>
    </tr>
    </table>
    ";
            if ($pickupInfo->isRepeated) {
                $repeatedPickupData = $pickupInfo->repeatedData;
                $startDate = $repeatedPickupData->startDate ? date('d/m/Y', strtotime($repeatedPickupData->startDate)) : 'N/A';
                $endDate = $repeatedPickupData->startDate ? date('d/m/Y', strtotime($repeatedPickupData->endDate)) : 'N/A';
                $repeatedDays = $repeatedPickupData->repeatedType == "Daily" ? "Daily" : join(",", $repeatedPickupData->days);
                $nextDate = $repeatedPickupData->nextpickupDate ? $repeatedPickupData->nextpickupDate : 'N/A';
                echo "

        <p class='repetition-info-title'>        Repetition Info
            </p>
        <table class='order-details-table pickup-info-table'>
        <tr>
        <th>Start date</th>
        <th>End date</th>
        <th>Repetition type</th>
        <th>Next pickup date</th>
     </tr>
     <tr>
     <td class='last-field'>  $startDate</td>
     <td class='last-field'>  $endDate</td>
     <td class='last-field'>  $repeatedDays</td>
     <td class='last-field'>  $nextDate</td>
  </tr>
        </table>";
            }
            echo "<p class='create-pickup-title'>   Total Pickups ($deliveries)  </p>
    ";

            if (count($pickupInfo->deliveryTrackingNumbers) > 0) {
                $url = 'https://app.bosta.co/api/v0/deliveries/search?trackingNumbers=' . join(',', $pickupInfo->deliveryTrackingNumbers);
                $result = wp_remote_post($url, array(
                    'timeout' => 30,
                    'method' => 'GET',
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'authorization' => $APIKey,
                        'X-Requested-By' => 'WooCommerce',
                    ),
                    'body' => json_encode($pickupData),
                ));
                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                    echo "Something went wrong: $error_message";
                } else {
                    $pickupDelivries = json_decode($result['body'])->deliveries;
                    echo " <table class='pickups-table'>
                    <tr>
                       <th>Tracking Number</th>
                       <th>Type</th>
                       <th>	Customer Info </th>
                       <th>	Dropoff Location</th>
                       <th>	COD </th></tr>";

                    for ($counter = 0; $counter < count($pickupDelivries); $counter++) {
                        $delivery = $pickupDelivries[$counter];
                        $deliveryType = $pickupDelivries[$counter]->type->value;
                        $receiver = $pickupDelivries[$counter]->receiver;
                        $dropOffAddress = $pickupDelivries[$counter]->dropOffAddress;
                        $dropOffAddressDistrict = $dropOffAddress->district->name;
                        $dropOffAddressZone = $dropOffAddress->zone->name;
                        $dropOffAddressCity = $dropOffAddress->city->name;
                        echo "<tr><td>$delivery->trackingNumber</td>
<td> $deliveryType</td>
<td>$receiver->firstName $receiver->lastName <br/>$receiver->phone</td>
<td>      $dropOffAddressDistrict - $dropOffAddressZone,  $dropOffAddressCity  </td>
<td>  $delivery->cod LE</td></tr>";
                    }
                }
            }

        }
    } else if ('admin.php' === $pagenow && $_GET['page'] = 'bosta-woocommerce-view-pickups' && !$_GET['pickupId']) {
        ?>   <button class="primary-button" onClick="document.location.href='admin.php?page=bosta-woocommerce-create-edit-pickup'">Create Pickup</button><?php
if ($_GET['state'] == 'history-pickups') {
            ?>

            <div class="pickups-page-tabs">
               <button class="tablink " onClick="document.location.href='admin.php?page=bosta-woocommerce-view-pickups'"  id="defaultOpen">Upcoming pickups</button>
               <button class="tablink ActiveTab" onClick="document.location.href='admin.php?page=bosta-woocommerce-view-pickups&&state=history-pickups'">History pickups</button>
            </div>
            <?php
} else {
            ?>
        <div class="pickups-page-tabs">
           <button class="tablink ActiveTab" onClick="document.location.href='admin.php?page=bosta-woocommerce-view-pickups&&state=upcoming-pickups'"  id="defaultOpen">Upcoming pickups</button>
           <button class="tablink" onClick="document.location.href='admin.php?page=bosta-woocommerce-view-pickups&&state=history-pickups'">History pickups</button>
        </div>
        <?php
}
        if ($_GET['state'] != 'history-pickups' || !isset($_GET['state'])) {
            $url = 'https://app.bosta.co/api/v0/pickups/search?state=Requested,Arrived at business,Route Assigned,Picking up,Receiving&pageId=-1';
        } else {
            $url = 'https://app.bosta.co/api/v0/pickups/search?state=Canceled,Picked up&pageId=-1';
        }
        $result = wp_remote_post($url, array(
            'timeout' => 30,
            'method' => 'GET',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
            'body' => json_encode($pickupData),
        ));

        $result = json_decode($result['body']);
        if ($result->success === false) {
            echo "<div class='error'>" . $result->message . "<div>";
        } else {
            $count = $result
                ->result->count;
            $pickups = $result
                ->result->pickups;
            $checkIfActionButtonNeeded = $_GET['state'] == 'history-pickups' ? false : true;
            echo "        <h4 class='pickup-table-title'>Pickup Requests</h4>
           <h3 class='pickup-table-subtitle'>Total Pickups ($count)<h3>
           <table class='pickups-table'>
              <tr>
                 <th>	Pickup Id</th>
                 <th>	Pickup location</th>
                 <th>Scheduled date </th>
                 <th>Pickup type </th>
                 <th>	Status </th>";
            if ($checkIfActionButtonNeeded) {
                echo "<th>Action </th>";
            }
            echo "</tr>
              ";
            for ($counter = 0; $counter < count($pickups); $counter++) {
                $id = $pickups[$counter]->_id;
                $puid = $pickups[$counter]->puid;
                $pickupLocationName = $pickups[$counter]->locationName ? $pickups[$counter]->locationName : 'N/A';
                $pickupLocation_city = $pickups[$counter]
                    ->business
                    ->address
                    ->city->name;
                $pickupLocation_zone = $pickups[$counter]
                    ->business
                    ->address
                    ->zone->name;
                $pickupLocation_district = $pickups[$counter]
                    ->business
                    ->address
                    ->district->name;
                $packageType = $pickups[$counter]
                    ->repeatedData->repeatedType;
                $scheduledDate = $pickups[$counter]->scheduledDate;

                switch ($pickups[$counter]->state) {
                    case "Requested":
                        $state = 'Created';
                        break;
                    case "Route Assigned":
                        $state = 'In progress';
                        break;
                    case "Picking up":
                        $state = 'In progress';
                        break;
                    case "Picked up":
                        $state = 'Picked up';
                        break;
                    case "Canceled":
                        $state = 'Canceled';
                        break;
                    default:
                        $state = $pickups[$counter]->state;
                };
                $state_class_name = strtolower($state);

                echo "<tr>
              <td><a href='admin.php?page=bosta-woocommerce-view-pickups&&pickupId=$id '' id='myBtn'>$puid</a></td>
              <td >$pickupLocationName </br>$pickupLocation_zone -   $pickupLocation_district , $pickupLocation_city </td>
              <td >    $scheduledDate</td>
              <td >  $packageType</td>
              <td > <span class='pickup_state_$state_class_name'>$state<span></td><td>";
                if ($checkIfActionButtonNeeded) {
                    echo '<a href="admin.php?page=bosta-woocommerce-create-edit-pickup&&pickupId=' . $id . '">Edit pickup</a>';

                }
                echo "</td>
              </tr>";
            }
            echo "</table>";
        }
        ;
    }
}