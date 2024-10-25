<?php
declare(strict_types=1);

class Producer
{
    private RdKafka\Producer $producer;
    private RdKafka\Topic $topic;
    private PDO $db;

    private const STATUS_PENDING = 'PENDING';
    private const STATUS_PROCESSING = 'PROCESSING';
    private const STATUS_COMPLETED = 'COMPLETED';
    private const STATUS_FAILED = 'FAILED';

    public function __construct()
    {
        // kafka 설정
        $conf = new RdKafka\Conf();
        $conf->set('metadata.broker.list', 'kafka:9092');

        $this->producer = new RdKafka\Producer($conf);
        $this->topic = $this->producer->newTopic('payment-complete');

        // DB 연결
        $this->db = new PDO(
            'mysql:host=producer-db;dbname=producer',
            'user',
            'P@ssw0rd',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function getPendingOrders(): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, GROUP_CONCAT(
                JSON_OBJECT(
                    'product_id', oi.product_id,
                    'name', oi.product_name,
                    'quantity', oi.quantity,
                    'price', oi.price
                )
            ) as items
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.status = :status
            GROUP BY o.id
            LIMIT 5
        ");
        
        $stmt->execute(['status' => self::STATUS_PENDING]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 주문상태 변경
     */
    public function updateOrderStatus(string $orderId, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = :status 
            WHERE order_id = :order_id
        ");
        
        $stmt->execute([
            'status' => $status,
            'order_id' => $orderId
        ]);
    }

    public function processOrders(): void
    {
        $orders = $this->getPendingOrders();
        
        foreach ($orders as $order) {
            try {
                $this->updateOrderStatus($order['order_id'], self::STATUS_PROCESSING);

                $paymentData = [
                    'order_id' => $order['order_id'],
                    'payment_time' => date('Y-m-d H:i:s'),
                    'user_id' => $order['user_id'],
                    'amount' => $order['total_amount'],
                    'payment_method' => $order['payment_method'],
                    'items' => json_decode('[' . $order['items'] . ']', true)
                ];

                $this->topic->produce(
                    RD_KAFKA_PARTITION_UA, 
                    0, 
                    json_encode($paymentData)
                );
                
                $this->updateOrderStatus($order['order_id'], self::STATUS_COMPLETED);
                echo "Payment sent: " . $order['order_id'] . " (COMPLETED)\n";
                
            } catch (Exception $e) {
                $this->updateOrderStatus($order['order_id'], self::STATUS_FAILED);
                echo "Payment failed: " . $order['order_id'] . " (ERROR: " . $e->getMessage() . ")\n";
            }
        }
        
        $this->producer->flush(1000);
    }

}

$producer = new Producer();

while (true) {
    $producer->processOrders();
    sleep(10);
}