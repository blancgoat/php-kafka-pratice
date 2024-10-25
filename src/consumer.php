<?php
declare(strict_types=1);

class Consumer
{
    private RdKafka\KafkaConsumer $consumer;
    private PDO $db;

    public function __construct()
    {
        // Kafka 설정
        $conf = new RdKafka\Conf();
        $conf->set('metadata.broker.list', 'kafka:9092');
        $conf->set('group.id', 'consumer-group');
        $conf->set('auto.offset.reset', 'earliest');

        $this->consumer = new RdKafka\KafkaConsumer($conf);
        $this->consumer->subscribe(['payment-complete']);

        // DB 연결
        $this->db = new PDO(
            'mysql:host=consumer-db;dbname=consumer',
            'user',
            'P@ssw0rd',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function saveOrder(array $paymentData): void
    {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO received_orders 
                (order_id, user_id, total_amount, payment_method)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $paymentData['order_id'],
                $paymentData['user_id'],
                $paymentData['amount'],
                $paymentData['payment_method']
            ]);

            // 주문 상품 정보 저장
            $stmt = $this->db->prepare("
                INSERT INTO received_order_items
                (order_id, product_id, product_name, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($paymentData['items'] as $item) {
                $stmt->execute([
                    $paymentData['order_id'],
                    $item['product_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price']
                ]);
            }

            $this->db->commit();
            echo "Order saved to database: {$paymentData['order_id']} \n";
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function processPayments(): void
    {
        echo "Waiting for payment messages...\n";

        while (true) {
            $message = $this->consumer->consume(120*1000);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $paymentData = json_decode($message->payload, true);
                    echo "\nReceived payment data:\n";
                    echo "Order ID: {$paymentData['order_id']}\n";
                    echo "Amount: {$paymentData['amount']}\n";
                    echo "User ID: {$paymentData['user_id']}\n";
                    
                    $this->saveOrder($paymentData);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    echo "No more messages; will wait for more\n";
                    break;
                    
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    echo "Timed out\n";
                    break;
                    
                default:
                    throw new Exception($message->errstr(), $message->err);
            }
        }
    }
}

(new Consumer())->processPayments();
