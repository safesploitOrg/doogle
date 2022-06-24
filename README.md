[MonoBlockchain](https://github.com/safesploit/MonoBlockchain)

# MonoBlockchain

MonoBlockchain is a decentralised proof of work cryptocurrency written in Python3. 

Individually referred to as an _MB Coin_ (Mono Block Coin).



<p align="center">
    <img alt="MonoBlockchain-Preview" src="#">
</p>

# Features

- Immutable ledger
- Distributed P2P network
- Proof of Work
  - SHA256
- Consensus protocol
- API
- Easy to use transaction system

# Table of Contents
- [Setup and Usage](#setup-and-usage)
  - [Python Version](#python-version)
  - [Dependencies](#dependencies)
  - [Initialising Servers](#initialising-servers)
- [Blockchain Concepts](#blockchain-concepts)
  - [Proof of Work](#proof-of-work)
  - [Hashing Algorithm](#hashing-algorithm)
  - [Immutable Ledger](#immutable-ledger)
  - [Distributed P2P](#distributed-p2p)
  - [Mining](#mining)
  - [Consensus Protocol](#consensus-protocol)
- [Programming Logic](#programming-logic)
  - [How Mining Works (Technical)](#how-mining-works-technical)
  - [User Interface](#user-interface)
- [Preview Images](#preview-images)
- [Preview Video](#preview-video)

[ToC Markdown Generator](https://toc.git.safesploit.com)

# Setup and Usage

## Python Version

    $ python3.10
    Python 3.10.5


## Dependencies

    pip3.10 install Crypto \
                    Flask \



## Initialising Servers

Initialisation can be done in two-ways. One is via PyCharm the other is running multiple Python instances via the command-line.

### PyCharm

#### Node1 (Server)

Within PyCharm under _Run/Debug Configurations > Add New Configuration > Python_ the following Configuration should be chosen:

  - Name: Node1
  - Script path: ~/PycharmProjects/MonoBlockchain/blockchain/blockchain.py
  - Parameters: _Leave this blank
  - Python interpreter: Python 3.10

  <p align="center">
  <img width="1348" alt="PyCharm Configuration Node1" src="https://user-images.githubusercontent.com/10171446/174770301-9f283c31-851c-4e78-bf2a-b0a04b3527a9.png">
  </br>
  <b>PyCharm Configuration Node1</b>
</p>

#### Alice (Client)

  - Name: Alice
  - Script path: ~/PycharmProjects/MonoBlockchain/blockchain_client/blockchain_client.py
  - Parameters: -p 8081
  - Python interpreter: Python 3.10

<p align="center">
  <img width="1196" alt="PyCharm Configuration Alice" src="https://user-images.githubusercontent.com/10171446/174770596-5742d253-496b-4357-a4a0-698de77659f8.png">
  </br>
  <b>PyCharm Configuration Alice</b>
</p>

#### Bob (Client)

  - Name: Bob
  - Script path: ~/PycharmProjects/MonoBlockchain/blockchain_client/blockchain_client.py
  - Parameters: -p 8082
  - Python interpreter: Python 3.10

<p align="center">
  <img width="1196" alt="PyCharm Configuration Bob" src="https://user-images.githubusercontent.com/10171446/174770973-8e59cb41-9e6b-4e30-a6f0-40350dd86935.png">
  </br>
  <b>PyCharm Configuration Bob</b>
</p>

#### Running the Configuration

<p align="center">
  <img width="1326" alt="PyCharm Configurations" src="https://user-images.githubusercontent.com/10171446/174771586-566e19c6-fb7c-4ddb-8dd8-31a17f7ac28e.png">

  </br>
  <b></b>
</p>

PyCharm Configurations are outlined in greater detail [here](https://www.jetbrains.com/help/pycharm/configuring-python-interpreter.html#packages).


### Command-Line

CMD.exe / Terminal / Shell

#### Node1 (server)

    $ python3 ~/MonoBlockchain/blockchain/blockchain.py


#### Alice (client)

    $ python3 ~/MonoBlockchain/blockchain-client/blockchain-client.py -p 8081

#### Bob (client)

    $ python3 ~/MonoBlockchain/blockchain-client/blockchain-client.py -p 8082


# Blockchain Concepts

## Proof of Work

MonoBlockchain is based on Proof of Work (PoW), _explanation_.

## Hashing Algorithm

PoW relies on SHA256 due to requiring:
  - One-way function
    - [The avalanche effect](https://www.cryptovision.com/en/glossary/avalanche-effect/#:~:text=The%20Avalanche%20Effect%20refers%20to,show%20a%20strong%20avalanche%20effect.)
    - [Deterministic](https://www.sqlite.org/deterministic.html#:~:text=A%20deterministic%20function%20always%20gives,input%20X%20is%20the%20same.)
  - Fast computation
  - Must withstand collisions (SHA-256 has 2<sup>256</sup> combinations)

## Immutable Ledger

The idea of an immutable ledger is to ensure the previous hash is linked cryptographically to the last block. Which then can be traversed back to the genesis (initial) block.

<p align="center">
  <img width="640" alt="Immutable Ledger Example" src="https://user-images.githubusercontent.com/10171446/172579310-c11ca268-f185-4560-8b89-5388aa17dabb.png">
  </br>
  <b>Immputable Ledger</b>
</p>

If _block 2_ were to be maliciously altered, the previous hash on _block 3_ would reflect this alteration. As the hash of _block 2_ would not be equal to the previous hash of _block 3_.

## Distributed P2P

### Explanation of Distributed P2P

Distributed peer-to-peer (P2P) network ensures the network hosting the blockchain ledger is not centralised located. 

<p align="center">
  <img width="575" alt="image" src="https://user-images.githubusercontent.com/10171446/172582471-6d101052-4e95-4482-b3f8-6c6bd120bf1e.png">
  </br>
  <b>Distributed P2P Network: Showing Computers (Servers) as Nodes</b>
</p>

Having a decentralised network provides several benefits:
  - More nodes in the network
  - Potentially faster as not relying on a single node
  - More secure as the ledger has no single point of failure


<p align="center">
  <img width="681" alt="Distributed P2P Network Showing Blocks" src="https://user-images.githubusercontent.com/10171446/172584273-9f9cdf41-b5d2-4727-b232-eebe8802473c.png">
  </br>
  <b>Distributed P2P Network Showing Blocks</b>
</p>

### Attacking Distributed P2P Network

Because of the immutable ledger, the attack must modify earlier blocks to reflect the hash change. Which requires a great deal of processing power as the SHA-256 algorithm is computationally demanding.

The attack vector of computing forged blocks is demonstrated below:

<p align="center">
  <img width="682" alt="Distributed P2P Network Being Attacked" src="https://user-images.githubusercontent.com/10171446/172584901-121923b0-2890-41ad-8f0f-d78d8d447461.png">
  </br>
  <b>Distributed P2P Network Being Attacked</b>
</p>

However, for the example above, seven nodes maintain an independent version of the ledger. Moreover, while the attacker has successfully modify blocks and forged the ledger cryptographically to reflect an action which did not take place. However, the attack did this for a single node. Hence, the attacker only makes up 14% of the distributed P2P network. For the attacker to successfully perform their attack, they must control 51% or more of the network's nodes. 

See, [51% attack](https://www.investopedia.com/terms/1/51-attack.asp#:~:text=A%2051%25%20attack%20is%20an,other%20miners%20from%20completing%20blocks.).

## Mining

Mining is the competitive process that verifies and adds new transactions to the blockchain for a cryptocurrency that uses the proof of work (PoW) method. The miner that wins the competition is rewarded with some amount of the currency and/or transaction fees - [source](https://www.pcmag.com/encyclopedia/term/crypto-mining#:~:text=(CRYPTOcurrency%20mining)%20The%20competitive%20process,currency%20and%2For%20transaction%20fees.).

Consider further reading [PoW - Wikipedia](https://en.wikipedia.org/wiki/Proof_of_work).

The main point with mining is _hard to solve, easy to verify_.


### Explanation of How Mining Works (Abstract)
[Bitcoin Mining in 4 Minutes - Computerphile](https://www.youtube.com/watch?v=wTC31ZI6QM4) will give a ver clear outline of Bitcoin mining. MonoBlockchain is based on the same concept and the PoW hashing algorithm is also SHA-256. So, there is little difference from a mining perspective between Bitcoin and MonoBlockchain.

[Nonce](https://en.wikipedia.org/wiki/Cryptographic_nonce) (number once) is an arbitrary number that can be used just once in a cryptographic communication. The nonce is used to ensure old communications cannot be reused.

<p align="center">
  <img width="464" alt="Abstract Overview of a Block" src="https://user-images.githubusercontent.com/10171446/172814804-7b06b2ad-6641-44d7-9034-cdc395bd8867.png">
  </br>
  <b>Abstract Overview of a Block</b>
</p>



### Why is Mining Necessary?

The short answer is to prevent abuse of the network. Requiring computational power to _prove work_ is a logical method for mitigating DoS attacks while still keeping the usage of the network feasible.


<p align="center">
  
  </br>
  <b></b>
</p>


<p align="center">
  
  </br>
  <b>Mining</b>
</p>

## Consensus Protocol

The purpose of a consensus protocol is to achieve consensus between participants as to what a blockchain should contain at a given time (including new blocks).

### First Challenege

Referring back to _Attacking Distributed P2P Network_, we saw the attacker achieved a tampered version of the blockchain, but only contributed 14% of the entire distributed network. The other 86% naturally outnumbered the malicious blockchain node. 


### Second Challenege

Because each node in the distributed P2P network will mine the next block independently, a problem arises; overlapping nodes during synchronisation.

<p align="center">
  <img width="661" alt="Second Challenege to Overcome" src="https://user-images.githubusercontent.com/10171446/173308045-ce98925e-ed15-4c2d-a586-6266bbd8b0fb.png">
  </br>
  <b>Consensus Protocol - Second Challenege: Overlapping Nodes</b>
</p>

The consensus to avoid overlapping nodes is to wait for a new block to be mined before synchronising with other nodes in the network. Once a node has mined a new block, the other nodes will be asked to add it to their blockchain.

Essentially, _the longest chain wins_ is an consensus between nodes.

#### Orphan Block

Another issue to be considered as a user of a blockchain PoW network are orphan blocks.

An [orphan block](https://www.investopedia.com/terms/o/orphan-block-cryptocurrency.asp#:~:text=An%20orphan%20block%20is%20a,the%20shorter%20chain%20are%20orphaned.) is a block that has been solved within the blockchain network but was not accepted by the network.

An orphan block can occur because, _there can be two miners who solve valid blocks simultaneously. The network uses both blocks until one chain has more verified blocks than the other. Then, the blocks in the shorter chain are orphaned._

Ideally, to avoid falling victim to an orphan block, users would be adviced to wait 4-6 blocks after their transaction was verified, before considering the transaction as 'full' verified.


<p align="center">
  
  </br>
  <b></b>
</p>

# Programming Logic

## How Mining Works (Technical)

## User Interface

To reduce development time with the user interface (UI), I have opted for using HTML with frameworks.

### HTML


### JavaScript

### Frameworks

The following frameworks are used:

  - Bootstrap v4.0.0 
  - DataTables 1.10.16
  - Font Awesome 4.7.0 
    - Font Awesome webfont
  - jQuery JavaScript Library v3.3.1

### CDNs and SRI Hashes



# Preview Images

# Preview Video
